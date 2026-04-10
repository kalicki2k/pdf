<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;

final readonly class StringBinaryDataSource implements BinaryDataSource
{
    public function __construct(
        private string $bytes,
    ) {
    }

    public function length(): int
    {
        return strlen($this->bytes);
    }

    public function contents(): string
    {
        return $this->bytes;
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
