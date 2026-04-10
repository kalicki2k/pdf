<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Encryption\Object;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Encryption\Stream\AesCbcStreamingByteEncryptor;
use Kalle\Pdf\Internal\Encryption\Stream\PassthroughStreamingByteEncryptor;
use Kalle\Pdf\Internal\Encryption\Stream\Rc4StreamingByteEncryptor;
use Kalle\Pdf\Internal\Encryption\Stream\StreamingByteEncryptor;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use RuntimeException;

final readonly class StandardObjectEncryptor
{
    public function __construct(
        private EncryptionProfile $profile,
        private StandardSecurityHandlerData $securityHandlerData,
    ) {
    }

    public function supportsObjectEncryption(): bool
    {
        return in_array($this->profile->algorithm, [
            EncryptionAlgorithm::RC4_40,
            EncryptionAlgorithm::RC4_128,
            EncryptionAlgorithm::AES_128,
            EncryptionAlgorithm::AES_256,
        ], true);
    }

    public function encryptString(int $objectId, string $value): string
    {
        return $this->encryptBytes($objectId, $value);
    }

    public function encryptedByteLength(int $plainLength): int
    {
        if (!$this->supportsObjectEncryption()) {
            return $plainLength;
        }

        return match ($this->profile->algorithm) {
            EncryptionAlgorithm::RC4_40,
            EncryptionAlgorithm::RC4_128 => $plainLength,
            EncryptionAlgorithm::AES_128,
            EncryptionAlgorithm::AES_256 => 16 + $plainLength + (16 - ($plainLength % 16)),
            default => throw new InvalidArgumentException('Unsupported encryption algorithm for object encryption.'),
        };
    }

    public function createStreamEncryptor(int $objectId): StreamingByteEncryptor
    {
        if (!$this->supportsObjectEncryption()) {
            return new PassthroughStreamingByteEncryptor();
        }

        return match ($this->profile->algorithm) {
            EncryptionAlgorithm::RC4_40,
            EncryptionAlgorithm::RC4_128 => new Rc4StreamingByteEncryptor($this->deriveObjectKey($objectId)),
            EncryptionAlgorithm::AES_128 => new AesCbcStreamingByteEncryptor(
                $this->deriveObjectKey($objectId, addAesSalt: true),
                'aes-128-cbc',
                'aes-128-ecb',
            ),
            EncryptionAlgorithm::AES_256 => new AesCbcStreamingByteEncryptor(
                $this->securityHandlerData->encryptionKey,
                'aes-256-cbc',
                'aes-256-ecb',
            ),
            default => throw new InvalidArgumentException('Unsupported encryption algorithm for object encryption.'),
        };
    }

    private function encryptBytes(int $objectId, string $value): string
    {
        $encryptor = $this->createStreamEncryptor($objectId);

        return $encryptor->write($value) . $encryptor->finish();
    }

    private function deriveObjectKey(int $objectId, bool $addAesSalt = false): string
    {
        $objectBytes = substr(pack('V', $objectId), 0, 3);
        $generationBytes = pack('v', 0);
        $material = $this->securityHandlerData->encryptionKey . $objectBytes . $generationBytes;

        if ($addAesSalt) {
            $material .= 'sAlT';
        }

        $hash = md5($material, true);

        return substr(
            $hash,
            0,
            min(strlen($this->securityHandlerData->encryptionKey) + 5, 16),
        );
    }

    public function encryptStreamObject(string $renderedObject, int $objectId): string
    {
        if (!$this->supportsObjectEncryption()) {
            return $renderedObject;
        }

        $streamMarker = 'stream' . PHP_EOL;
        $streamOffset = strpos($renderedObject, $streamMarker);

        if ($streamOffset === false) {
            return $renderedObject;
        }

        $dataStart = $streamOffset + strlen($streamMarker);
        $streamEndMarker = PHP_EOL . 'endstream';
        $dataEnd = strpos($renderedObject, $streamEndMarker, $dataStart);

        if ($dataEnd === false) {
            throw new RuntimeException('Unable to locate stream end marker in rendered object.');
        }

        $streamData = substr($renderedObject, $dataStart, $dataEnd - $dataStart);
        $encryptedData = $this->encryptBytes($objectId, $streamData);
        $updatedObject = substr($renderedObject, 0, $dataStart)
            . $encryptedData
            . substr($renderedObject, $dataEnd);

        if (strlen($encryptedData) !== strlen($streamData)) {
            $updatedLengthObject = preg_replace(
                '/\/Length\s+\d+/',
                '/Length ' . strlen($encryptedData),
                $updatedObject,
                1,
            );

            if (is_string($updatedLengthObject)) {
                $updatedObject = $updatedLengthObject;
            }
        }

        return $updatedObject;
    }
}
