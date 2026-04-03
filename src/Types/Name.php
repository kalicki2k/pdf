<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

final readonly class Name implements Value
{
    public function __construct(private string $value)
    {
    }

    public function render(): string
    {
        return '/' . $this->value;
    }
}
