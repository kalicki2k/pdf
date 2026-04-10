<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;

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

    final public function write(PdfOutput $output): void
    {
        $this->writeObject($output);
    }

    final public function writeWithStringEncryptor(PdfOutput $output, ?ObjectStringEncryptor $encryptor = null): void
    {
        if ($encryptor === null) {
            $this->writeObject($output);

            return;
        }

        $this->writeObjectWithStringEncryptor($output, $encryptor);
    }

    final public function render(): string
    {
        $buffer = new StringPdfOutput();
        $this->write($buffer);

        return $buffer->contents();
    }

    final public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
    {
        $buffer = new StringPdfOutput();
        $this->writeWithStringEncryptor($buffer, $encryptor);

        return $buffer->contents();
    }

    protected function writeObjectWithStringEncryptor(PdfOutput $output, ObjectStringEncryptor $encryptor): void
    {
        $this->writeObject($output);
    }

    protected function renderDictionaryObject(
        DictionaryType $dictionary,
        ?ObjectStringEncryptor $encryptor = null,
    ): string {
        $buffer = new StringPdfOutput();
        $this->writeDictionaryObject($buffer, $dictionary, $encryptor);

        return $buffer->contents();
    }

    protected function writeDictionaryObject(
        PdfOutput $output,
        DictionaryType $dictionary,
        ?ObjectStringEncryptor $encryptor = null,
    ): void {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $dictionary->write($output, $encryptor);
        $output->write(PHP_EOL);
        $output->write('endobj' . PHP_EOL);
    }

    abstract protected function writeObject(PdfOutput $output): void;
}
