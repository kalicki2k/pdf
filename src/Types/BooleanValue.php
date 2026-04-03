<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

final readonly class BooleanValue implements Value
{
    public function __construct(private bool $value)
    {
    }

    public function render(): string
    {
        return $this->value ? 'true' : 'false';
    }
}
