<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\HasDeferredStreamLengthObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Object\StreamLengthObject;
use Kalle\Pdf\Page\Content\Instruction\ContentInstruction;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\CountingPdfOutput;
use Kalle\Pdf\Render\EncryptingPdfOutput;
use Kalle\Pdf\Render\MeasuringPdfOutput;
use Kalle\Pdf\Render\PdfOutput;

class Contents extends IndirectObject implements EncryptableIndirectObject, HasDeferredStreamLengthObject
{
    /** @var list<ContentInstruction> */
    private array $elements = [];

    private ?StreamLengthObject $lengthObject = null;

    public function addElement(ContentInstruction $element): self
    {
        $this->elements[] = $element;

        return $this;
    }

    protected function writeObject(PdfOutput $output): void
    {
        if ($this->lengthObject !== null) {
            $this->writeObjectWithDeferredLength($output);

            return;
        }

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->dictionary($this->synchronizeLengthObject())->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->writeContentsTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        if ($this->lengthObject !== null) {
            $this->writeEncryptedWithDeferredLength($output, $objectEncryptor);

            return;
        }

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->dictionary($objectEncryptor->encryptedByteLength($this->synchronizeLengthObject()))->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $encryptedOutput = new EncryptingPdfOutput(
            $output,
            $objectEncryptor->createStreamEncryptor($this->id),
        );
        $this->writeContentsTo($encryptedOutput);
        $encryptedOutput->finish();
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

    private function writeObjectWithDeferredLength(PdfOutput $output): void
    {
        $lengthObject = $this->lengthObject;
        assert($lengthObject instanceof StreamLengthObject);

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->dictionary(0)->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);

        $contentOutput = new MeasuringPdfOutput($output);
        $this->writeContentsTo($contentOutput);
        $lengthObject->setLength($contentOutput->writtenBytes());

        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function writeEncryptedWithDeferredLength(
        PdfOutput $output,
        StandardObjectEncryptor $objectEncryptor,
    ): void {
        $lengthObject = $this->lengthObject;
        assert($lengthObject instanceof StreamLengthObject);

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->dictionary(0)->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);

        $encryptingTarget = new MeasuringPdfOutput($output);
        $encryptedOutput = new EncryptingPdfOutput(
            $encryptingTarget,
            $objectEncryptor->createStreamEncryptor($this->id),
        );
        $this->writeContentsTo($encryptedOutput);
        $encryptedOutput->finish();
        $lengthObject->setLength($encryptingTarget->writtenBytes());

        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }
}
