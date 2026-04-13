<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use Closure;
use RuntimeException;

final readonly class AesCbcCipher
{
    private const int IV_LENGTH = 16;

    private Closure $ivGenerator;

    public function __construct(?callable $ivGenerator = null)
    {
        $this->ivGenerator = $ivGenerator instanceof Closure
            ? $ivGenerator
            : Closure::fromCallable($ivGenerator ?? static fn (): string => random_bytes(16));
    }

    public function encrypt(string $key, string $plaintext): string
    {
        $iv = ($this->ivGenerator)();

        if (!is_string($iv)) {
            throw new RuntimeException('AES-CBC encryption requires a string initialization vector.');
        }

        if (strlen($iv) !== self::IV_LENGTH) {
            throw new RuntimeException('AES-CBC encryption requires a 16-byte initialization vector.');
        }

        $cipher = match (strlen($key)) {
            16 => 'aes-128-cbc',
            32 => 'aes-256-cbc',
            default => throw new RuntimeException('AES-CBC encryption requires a 16-byte or 32-byte key.'),
        };

        $encrypted = openssl_encrypt(
            $plaintext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($encrypted === false) {
            throw new RuntimeException(sprintf(
                'Unable to encrypt PDF object payload with %s.',
                $cipher,
            ));
        }

        return $iv . $encrypted;
    }
}
