<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

final class CountingPdfOutput implements PdfOutput
{
    private int $offset = 0;

    public function write(string $bytes): void
    {
        $this->offset += strlen($bytes);
    }

    public function offset(): int
    {
        return $this->offset;
    }
}
