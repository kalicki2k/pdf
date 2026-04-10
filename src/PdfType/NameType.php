<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;

final readonly class NameType implements Type
{
    public function __construct(private string $value)
    {
    }

    public function render(?ObjectStringEncryptor $encryptor = null): string
    {
        return '/' . $this->value;
    }
}
