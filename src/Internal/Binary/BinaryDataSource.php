<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Binary;

use Kalle\Pdf\Internal\Render\PdfOutput;

/**
 * @internal Models a reusable binary source for PDF stream payloads.
 */
interface BinaryDataSource
{
    public function length(): int;

    public function contents(): string;

    public function writeTo(PdfOutput $output): void;

    public function close(): void;
}
