<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

final class Raw extends Element
{
    public function __construct(private readonly string $content)
    {
    }

    public function render(): string
    {
        return $this->content;
    }
}
