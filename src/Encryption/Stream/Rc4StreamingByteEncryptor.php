<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption\Stream;

final class Rc4StreamingByteEncryptor implements StreamingByteEncryptor
{
    /** @var array<int, int> */
    private array $state;

    private int $i = 0;

    private int $j = 0;

    public function __construct(string $key)
    {
        $this->state = range(0, 255);
        $keyLength = strlen($key);
        $j = 0;

        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $this->state[$i] + ord($key[$i % $keyLength])) % 256;
            [$this->state[$i], $this->state[$j]] = [$this->state[$j], $this->state[$i]];
        }
    }

    public function write(string $bytes): string
    {
        $output = '';

        foreach (str_split($bytes) as $byte) {
            $this->i = ($this->i + 1) % 256;
            $this->j = ($this->j + $this->state[$this->i]) % 256;
            [$this->state[$this->i], $this->state[$this->j]] = [$this->state[$this->j], $this->state[$this->i]];
            $k = $this->state[($this->state[$this->i] + $this->state[$this->j]) % 256];
            $output .= chr(ord($byte) ^ $k);
        }

        return $output;
    }

    public function finish(): string
    {
        return '';
    }
}
