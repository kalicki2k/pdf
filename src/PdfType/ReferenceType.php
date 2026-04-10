<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;

final readonly class ReferenceType implements Type
{
    use RendersPdfType;

    public function __construct(private IndirectObject $value)
    {
    }

    public function write(PdfOutput $output, ?ObjectStringEncryptor $encryptor = null): void
    {
        $output->write($this->value->id . ' 0 R');
    }
}
