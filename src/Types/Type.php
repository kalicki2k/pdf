<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;

interface Type
{
    public function render(?ObjectStringEncryptor $encryptor = null): string;
}
