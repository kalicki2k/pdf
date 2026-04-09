<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Element\Element;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Types\DictionaryType;

final class Contents extends IndirectObject implements EncryptableIndirectObject
{
    /** @var list<Element> */
    private array $elements = [];

    public function addElement(Element $element): self
    {
        $this->elements[] = $element;

        return $this;
    }

    protected function writeObject(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary($this->contentsLength())->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->writeContentsTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedContents = $objectEncryptor->encryptString($this->id, $this->renderedContents());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($encryptedContents))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedContents);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function writeContentsTo(PdfOutput $output): void
    {
        if ($this->elements === []) {
            return;
        }

        foreach ($this->elements as $index => $element) {
            if ($index > 0) {
                $output->write(PHP_EOL);
            }

            $element->write($output);
        }
    }

    private function dictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Length' => $length,
        ]);
    }

    private function contentsLength(): int
    {
        $length = 0;

        foreach ($this->elements as $index => $element) {
            if ($index > 0) {
                $length += strlen(PHP_EOL);
            }

            $length += strlen($element->render());
        }

        return $length;
    }

    private function renderedContents(): string
    {
        if ($this->elements === []) {
            return '';
        }

        $buffer = new StringPdfOutput();
        $this->writeContentsTo($buffer);

        return $buffer->contents();
    }
}
