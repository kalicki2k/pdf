<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use InvalidArgumentException;

final class Aes256CbcNoPaddingCipher
{
    public function encrypt(string $plaintext, string $key): string
    {
        $encrypted = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            str_repeat("\x00", 16),
        );

        if ($encrypted === false) {
            throw new InvalidArgumentException('Unable to encrypt AES-256-CBC security handler payload.');
        }

        return $encrypted;
    }
}
