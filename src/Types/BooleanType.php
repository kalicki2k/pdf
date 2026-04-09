<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;

final readonly class BooleanType implements Type
{
    public function __construct(private bool $value)
    {
    }

    public function render(?ObjectStringEncryptor $encryptor = null): string
    {
        return $this->value ? 'true' : 'false';
    }
}
