<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use Closure;
use RuntimeException;

final class Aes128Cipher
{
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
            throw new RuntimeException('AES-128 encryption requires a string initialization vector.');
        }

        if (strlen($iv) !== 16) {
            throw new RuntimeException('AES-128 encryption requires a 16-byte initialization vector.');
        }

        $encrypted = openssl_encrypt(
            $plaintext,
            'aes-128-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($encrypted === false) {
            throw new RuntimeException('Unable to encrypt PDF object payload with aes-128-cbc.');
        }

        return $iv . $encrypted;
    }
}
