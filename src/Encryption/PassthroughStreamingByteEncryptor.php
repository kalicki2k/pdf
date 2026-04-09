<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

final class PassthroughStreamingByteEncryptor implements StreamingByteEncryptor
{
    public function write(string $bytes): string
    {
        return $bytes;
    }

    public function finish(): string
    {
        return '';
    }
}
