<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use RuntimeException;

final class ObjectEncryptor
{
    public function __construct(
        private readonly EncryptionProfile $profile,
        private readonly StandardSecurityHandlerData $securityHandlerData,
        private readonly Rc4Cipher $cipher = new Rc4Cipher(),
        private readonly Aes128Cipher $aes128Cipher = new Aes128Cipher(),
    ) {
    }

    public function encryptLiteralStrings(string $contents, int $objectId): string
    {
        return $this->encryptLiteralStringContents($contents, $objectId);
    }

    public function encryptStreamContents(string $contents, int $objectId): string
    {
        return $this->encryptBytes($objectId, $contents);
    }

    public function encryptObject(string $renderedObject, int $objectId): string
    {
        $streamMarker = "stream\n";
        $streamOffset = strpos($renderedObject, $streamMarker);

        if ($streamOffset === false) {
            return $this->encryptLiteralStringContents($renderedObject, $objectId);
        }

        $streamStart = $streamOffset + strlen($streamMarker);
        $streamEndMarker = "\nendstream";
        $streamEnd = strpos($renderedObject, $streamEndMarker, $streamStart);

        if ($streamEnd === false) {
            throw new RuntimeException('Unable to locate stream end marker in rendered object.');
        }

        $dictionary = substr($renderedObject, 0, $streamStart);
        $streamData = substr($renderedObject, $streamStart, $streamEnd - $streamStart);
        $suffix = substr($renderedObject, $streamEnd);
        $encryptedStreamData = $this->encryptStreamContents($streamData, $objectId);

        return $this->encryptLiteralStringContents($dictionary, $objectId)
            . $encryptedStreamData
            . $suffix;
    }

    private function encryptLiteralStringContents(string $contents, int $objectId): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            if ($contents[$offset] !== '(') {
                $result .= $contents[$offset];
                $offset++;

                continue;
            }

            [$literalEnd, $decoded] = $this->decodeLiteralString($contents, $offset);
            $result .= '(' . $this->encodeLiteralString($this->encryptBytes($objectId, $decoded)) . ')';
            $offset = $literalEnd + 1;
        }

        return $result;
    }

    /**
     * @return array{int, string}
     */
    private function decodeLiteralString(string $contents, int $startOffset): array
    {
        $decoded = '';
        $length = strlen($contents);
        $nesting = 0;
        $offset = $startOffset;

        while ($offset < $length) {
            $char = $contents[$offset];

            if ($char === '(') {
                $nesting++;

                if ($nesting > 1) {
                    $decoded .= '(';
                }

                $offset++;

                continue;
            }

            if ($char === ')') {
                $nesting--;

                if ($nesting === 0) {
                    return [$offset, $decoded];
                }

                $decoded .= ')';
                $offset++;

                continue;
            }

            if ($char !== '\\') {
                $decoded .= $char;
                $offset++;

                continue;
            }

            $offset++;

            if ($offset >= $length) {
                break;
            }

            $escaped = $contents[$offset];

            if ($escaped >= '0' && $escaped <= '7') {
                $octal = $escaped;

                for ($i = 0; $i < 2 && $offset + 1 < $length; $i++) {
                    $next = $contents[$offset + 1];

                    if ($next < '0' || $next > '7') {
                        break;
                    }

                    $offset++;
                    $octal .= $next;
                }

                $decoded .= chr(((int) octdec($octal)) & 0xFF);
                $offset++;

                continue;
            }

            $decoded .= match ($escaped) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0C",
                "\n", "\r" => '',
                default => $escaped,
            };
            $offset++;
        }

        throw new RuntimeException('Unable to parse PDF literal string for encryption.');
    }

    private function encodeLiteralString(string $bytes): string
    {
        $encoded = '';

        foreach (str_split($bytes) as $byte) {
            $value = ord($byte);

            $encoded .= match (true) {
                $byte === '\\' => '\\\\',
                $byte === '(' => '\(',
                $byte === ')' => '\)',
                $value >= 32 && $value <= 126 => $byte,
                default => sprintf('\\%03o', $value),
            };
        }

        return $encoded;
    }

    private function encryptBytes(int $objectId, string $bytes): string
    {
        return match ($this->profile->algorithm) {
            Algorithm::RC4_128 => $this->cipher->encrypt($this->deriveObjectKey($objectId), $bytes),
            Algorithm::AES_128 => $this->aes128Cipher->encrypt($this->deriveObjectKey($objectId, true), $bytes),
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
            min(intdiv($this->profile->keyLengthInBits, 8) + 5, 16),
        );
    }
}
