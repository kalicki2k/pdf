<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content\Instruction;

use Kalle\Pdf\Render\PdfOutput;

final class RawInstruction extends ContentInstruction
{
    public function __construct(private readonly string $content)
    {
    }

    protected function writeInstruction(PdfOutput $output): void
    {
        $output->write($this->content);
    }
}
