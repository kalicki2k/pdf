<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use InvalidArgumentException;

final class Aes256EcbNoPaddingCipher
{
    public function encrypt(string $plaintext, string $key): string
    {
        $encrypted = openssl_encrypt(
            $plaintext,
            'aes-256-ecb',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        );

        if ($encrypted === false) {
            throw new InvalidArgumentException('Unable to encrypt AES-256-ECB security handler payload.');
        }

        return $encrypted;
    }
}
