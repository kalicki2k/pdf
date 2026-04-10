<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;

/**
 * @internal Models a reusable binary source for PDF stream payloads.
 */
interface BinaryDataSource
{
    public function writeTo(PdfOutput $output): void;

    public function close(): void;
}
