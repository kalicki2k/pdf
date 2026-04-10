<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Render;

interface PdfOutput
{
    public function write(string $bytes): void;

    public function offset(): int;
}
