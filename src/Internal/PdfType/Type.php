<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\PdfType;

use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;

interface Type
{
    public function render(?ObjectStringEncryptor $encryptor = null): string;
}
