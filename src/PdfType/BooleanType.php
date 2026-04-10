<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Render\PdfOutput;

final readonly class BooleanType implements Type
{
    use RendersPdfType;

    public function __construct(private bool $value)
    {
    }

    public function write(PdfOutput $output, ?ObjectStringEncryptor $encryptor = null): void
    {
        $output->write($this->value ? 'true' : 'false');
    }
}
