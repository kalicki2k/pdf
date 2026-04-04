<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

final readonly class RawType implements Type
{
    public function __construct(private string $value)
    {
    }

    public function render(): string
    {
        return $this->value;
    }
}
