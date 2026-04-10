<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Render\StringPdfOutput;

trait RendersPdfType
{
    final public function render(?ObjectStringEncryptor $encryptor = null): string
    {
        $buffer = new StringPdfOutput();
        $this->write($buffer, $encryptor);

        return $buffer->contents();
    }
}
