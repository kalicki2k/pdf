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

    public function write(PdfOutput $output): void
    {
        $output->write($this->render());
    }

    abstract public function render(): string;
}
