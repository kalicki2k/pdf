<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content\Instruction;

final class RawInstruction extends ContentInstruction
{
    public function __construct(private readonly string $content)
    {
    }

    public function render(): string
    {
        return $this->content;
    }
}
