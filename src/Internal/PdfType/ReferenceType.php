<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\PdfType;

use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Object\IndirectObject;

final readonly class ReferenceType implements Type
{
    public function __construct(private IndirectObject $value)
    {
    }

    public function render(?ObjectStringEncryptor $encryptor = null): string
    {
        return $this->value->id . ' 0 R';
    }
}
