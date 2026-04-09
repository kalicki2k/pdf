<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;
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

    public function writeWithStringEncryptor(PdfOutput $output, ?ObjectStringEncryptor $encryptor = null): void
    {
        $output->write($this->renderWithStringEncryptor($encryptor));
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
    {
        return $this->render();
    }

    abstract public function render(): string;
}
