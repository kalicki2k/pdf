<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption\Stream;

use RuntimeException;

final class AesCbcStreamingByteEncryptor implements StreamingByteEncryptor
{
    private const BLOCK_BYTES = 16;

    private string $buffer = '';

    private string $iv;

    private string $previousBlock;

    private bool $ivEmitted = false;

    private bool $finished = false;

    public function __construct(
        private readonly string $key,
        private readonly string $cbcCipher,
        private readonly string $ecbCipher,
    ) {
        $this->iv = random_bytes(self::BLOCK_BYTES);
        $this->previousBlock = $this->iv;
    }

    public function write(string $bytes): string
    {
        $this->assertWritable();

        if ($bytes === '') {
            return '';
        }

        $this->buffer .= $bytes;
        $encrypted = '';

        while (strlen($this->buffer) > self::BLOCK_BYTES) {
            $encrypted .= $this->encryptBlock(substr($this->buffer, 0, self::BLOCK_BYTES));
            $this->buffer = substr($this->buffer, self::BLOCK_BYTES);
        }

        return $this->prefixIv($encrypted);
    }

    public function finish(): string
    {
        $this->assertWritable();

        $paddingLength = self::BLOCK_BYTES - (strlen($this->buffer) % self::BLOCK_BYTES);
        $this->buffer .= str_repeat(chr($paddingLength), $paddingLength);
        $encrypted = '';

        while ($this->buffer !== '') {
            $encrypted .= $this->encryptBlock(substr($this->buffer, 0, self::BLOCK_BYTES));
            $this->buffer = substr($this->buffer, self::BLOCK_BYTES);
        }

        $this->finished = true;

        return $this->prefixIv($encrypted);
    }

    private function encryptBlock(string $block): string
    {
        $xoredBlock = $block ^ $this->previousBlock;
        $encryptedBlock = openssl_encrypt(
            $xoredBlock,
            $this->ecbCipher,
            $this->key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        );

        if ($encryptedBlock === false) {
            throw new RuntimeException("Unable to encrypt PDF object payload with {$this->cbcCipher}.");
        }

        $this->previousBlock = $encryptedBlock;

        return $encryptedBlock;
    }

    private function prefixIv(string $encrypted): string
    {
        if ($this->ivEmitted) {
            return $encrypted;
        }

        $this->ivEmitted = true;

        return $this->iv . $encrypted;
    }

    private function assertWritable(): void
    {
        if ($this->finished) {
            throw new RuntimeException('Cannot write to a finished PDF stream encryptor.');
        }
    }
}
