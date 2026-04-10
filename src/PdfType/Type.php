<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Render\PdfOutput;

interface Type
{
    public function write(PdfOutput $output, ?ObjectStringEncryptor $encryptor = null): void;
}
