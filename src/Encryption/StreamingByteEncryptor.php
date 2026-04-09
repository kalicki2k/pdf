<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

interface StreamingByteEncryptor
{
    public function write(string $bytes): string;

    public function finish(): string;
}
