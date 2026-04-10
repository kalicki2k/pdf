<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Internal\Encryption\Stream\StreamingByteEncryptor;

final class EncryptingPdfOutput implements PdfOutput
{
    public function __construct(
        private readonly PdfOutput $output,
        private readonly StreamingByteEncryptor $encryptor,
    ) {
    }

    public function write(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $encrypted = $this->encryptor->write($bytes);

        if ($encrypted === '') {
            return;
        }

        $this->output->write($encrypted);
    }

    public function finish(): void
    {
        $encrypted = $this->encryptor->finish();

        if ($encrypted === '') {
            return;
        }

        $this->output->write($encrypted);
    }

    public function offset(): int
    {
        return $this->output->offset();
    }
}
