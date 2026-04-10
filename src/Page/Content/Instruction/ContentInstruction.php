<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content\Instruction;

use Kalle\Pdf\Render\PdfOutput;

abstract class ContentInstruction
{
    public float $x;
    public float $y;

    public function setPosition(float $x, float $y): self
    {
        $this->x = $x;
        $this->y = $y;

        return $this;
    }

    final public function write(PdfOutput $output): void
    {
        $this->writeInstruction($output);
    }

    abstract protected function writeInstruction(PdfOutput $output): void;
}
