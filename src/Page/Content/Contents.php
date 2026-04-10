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
use Kalle\Pdf\Render\EncryptingPdfOutput;
use Kalle\Pdf\Render\MeasuringPdfOutput;
use Kalle\Pdf\Render\PdfOutput;
use LogicException;

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
        $this->writeObjectWithDeferredLength($output, $this->requireLengthObject());
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $this->writeEncryptedWithDeferredLength($output, $objectEncryptor, $this->requireLengthObject());
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

    private function dictionary(StreamLengthObject $lengthObject): DictionaryType
    {
        return new DictionaryType([
            'Length' => new ReferenceType($lengthObject),
        ]);
    }

    private function requireLengthObject(): StreamLengthObject
    {
        if ($this->lengthObject === null) {
            throw new LogicException(sprintf(
                'Contents object %s requires a prepared length object before serialization.',
                static::class,
            ));
        }

        return $this->lengthObject;
    }

    private function writeObjectWithDeferredLength(PdfOutput $output, StreamLengthObject $lengthObject): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->dictionary($lengthObject)->write($output);
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
        StreamLengthObject $lengthObject,
    ): void {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->dictionary($lengthObject)->write($output);
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
