<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use InvalidArgumentException;

final class Rc4Cipher
{
    public function encrypt(string $key, string $plaintext): string
    {
        if ($key === '') {
            throw new InvalidArgumentException('RC4 encryption requires a non-empty key.');
        }

        $state = range(0, 255);
        $keyLength = strlen($key);
        $j = 0;

        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) % 256;
            [$state[$i], $state[$j]] = [$state[$j], $state[$i]];
        }

        $i = 0;
        $j = 0;
        $ciphertext = '';
        $length = strlen($plaintext);

        for ($index = 0; $index < $length; $index++) {
            $i = ($i + 1) % 256;
            $j = ($j + $state[$i]) % 256;
            [$state[$i], $state[$j]] = [$state[$j], $state[$i]];
            $keyByte = $state[($state[$i] + $state[$j]) % 256];
            $ciphertext .= chr((ord($plaintext[$index]) ^ $keyByte) & 0xFF);
        }

        return $ciphertext;
    }
}
