<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Render\PdfOutput;

abstract class IndirectObject
{
    public function __construct(
        public int $id {
            get {
                return $this->id;
            }
        },
    )
    {
    }

    public function write(PdfOutput $output): void
    {
        $output->write($this->render());
    }

    abstract public function render(): string;
}
