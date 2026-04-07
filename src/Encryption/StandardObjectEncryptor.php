<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use InvalidArgumentException;
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

    private function encryptBytes(int $objectId, string $value): string
    {
        if (!$this->supportsObjectEncryption()) {
            return $value;
        }

        return match ($this->profile->algorithm) {
            EncryptionAlgorithm::RC4_40,
            EncryptionAlgorithm::RC4_128 => (new Rc4Cipher())->encrypt($this->deriveObjectKey($objectId), $value),
            EncryptionAlgorithm::AES_128 => $this->encryptAes128($this->deriveObjectKey($objectId, addAesSalt: true), $value),
            EncryptionAlgorithm::AES_256 => $this->encryptAes256($this->securityHandlerData->encryptionKey, $value),
            default => throw new InvalidArgumentException('Unsupported encryption algorithm for object encryption.'),
        };
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

    private function encryptAes128(string $key, string $value): string
    {
        return $this->encryptAesCbc($key, $value, 'aes-128-cbc');
    }

    private function encryptAes256(string $key, string $value): string
    {
        return $this->encryptAesCbc($key, $value, 'aes-256-cbc');
    }

    private function encryptAesCbc(string $key, string $value, string $cipher): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $value,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($encrypted === false) {
            throw new RuntimeException("Unable to encrypt PDF object payload with {$cipher}.");
        }

        return $iv . $encrypted;
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
