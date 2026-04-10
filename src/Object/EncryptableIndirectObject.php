<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Render\PdfOutput;

interface EncryptableIndirectObject
{
    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void;
}
