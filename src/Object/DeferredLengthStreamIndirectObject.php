<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\EncryptingPdfOutput;
use Kalle\Pdf\Render\MeasuringPdfOutput;
use Kalle\Pdf\Render\PdfOutput;

abstract class DeferredLengthStreamIndirectObject extends StreamIndirectObject implements HasDeferredStreamLengthObject
{
    private ?StreamLengthObject $lengthObject = null;

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

    protected function writeObject(PdfOutput $output): void
    {
        if ($this->lengthObject === null) {
            parent::writeObject($output);

            return;
        }

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->streamDictionary(new ReferenceType($this->lengthObject))->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);

        $measuringOutput = new MeasuringPdfOutput($output);
        $this->writeStreamContents($measuringOutput);
        $this->lengthObject->setLength($measuringOutput->writtenBytes());

        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        if ($this->lengthObject === null) {
            parent::writeEncrypted($output, $objectEncryptor);

            return;
        }

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->streamDictionary(new ReferenceType($this->lengthObject))->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);

        $measuringOutput = new MeasuringPdfOutput($output);
        $encryptedOutput = new EncryptingPdfOutput(
            $measuringOutput,
            $objectEncryptor->createStreamEncryptor($this->id),
        );
        $this->writeStreamContents($encryptedOutput);
        $encryptedOutput->finish();
        $this->lengthObject->setLength($measuringOutput->writtenBytes());

        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }
}
