<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Element\Element;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\CountingPdfOutput;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\ReferenceType;

final class Contents extends IndirectObject implements EncryptableIndirectObject
{
    /** @var list<Element> */
    private array $elements = [];

    private ?StreamLengthObject $lengthObject = null;

    public function addElement(Element $element): self
    {
        $this->elements[] = $element;

        return $this;
    }

    protected function writeObject(PdfOutput $output): void
    {
        $length = $this->synchronizeLengthObject();

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary($length)->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->writeContentsTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedContents = $objectEncryptor->encryptString($this->id, $this->renderedContents());
        $length = strlen($encryptedContents);

        if ($this->lengthObject !== null) {
            $this->lengthObject->setLength($length);
        }

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary($length)->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedContents);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function prepareLengthObject(int $id): StreamLengthObject
    {
        if ($this->lengthObject === null) {
            $this->lengthObject = new StreamLengthObject($id);
        }

        return $this->lengthObject;
    }

    public function getLengthObject(): ?StreamLengthObject
    {
        return $this->lengthObject;
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
            'Length' => $this->lengthObject !== null
                ? new ReferenceType($this->lengthObject)
                : $length,
        ]);
    }

    private function synchronizeLengthObject(): int
    {
        $counter = new CountingPdfOutput();
        $this->writeContentsTo($counter);
        $length = $counter->offset();

        if ($this->lengthObject !== null) {
            $this->lengthObject->setLength($length);
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
