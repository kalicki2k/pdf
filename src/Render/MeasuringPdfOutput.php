<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

final class MeasuringPdfOutput implements PdfOutput
{
    private int $writtenBytes = 0;

    public function __construct(
        private readonly PdfOutput $output,
    ) {
    }

    public function write(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $this->output->write($bytes);
        $this->writtenBytes += strlen($bytes);
    }

    public function offset(): int
    {
        return $this->output->offset();
    }

    public function writtenBytes(): int
    {
        return $this->writtenBytes;
    }
}
