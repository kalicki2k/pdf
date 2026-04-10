<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;

final readonly class StringBinaryDataSource implements RandomAccessBinaryDataSource
{
    public function __construct(
        private string $bytes,
    ) {
    }

    public function length(): int
    {
        return strlen($this->bytes);
    }

    public function slice(int $offset, int $length): string
    {
        if ($offset < 0) {
            return '';
        }

        return substr($this->bytes, $offset, $length);
    }

    public function writeTo(PdfOutput $output): void
    {
        if ($this->bytes === '') {
            return;
        }

        $output->write($this->bytes);
    }

    public function close(): void
    {
    }
}
