<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption\Crypto;

final class Rc4Cipher
{
    public function encrypt(string $key, string $data): string
    {
        $state = range(0, 255);
        $keyLength = strlen($key);
        $j = 0;

        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) % 256;
            [$state[$i], $state[$j]] = [$state[$j], $state[$i]];
        }

        $i = 0;
        $j = 0;
        $output = '';

        foreach (str_split($data) as $byte) {
            $i = ($i + 1) % 256;
            $j = ($j + $state[$i]) % 256;
            [$state[$i], $state[$j]] = [$state[$j], $state[$i]];
            $k = $state[($state[$i] + $state[$j]) % 256];
            $output .= chr(ord($byte) ^ $k);
        }

        return $output;
    }
}
