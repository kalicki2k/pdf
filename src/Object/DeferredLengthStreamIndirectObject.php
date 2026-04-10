<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\EncryptingPdfOutput;
use Kalle\Pdf\Render\MeasuringPdfOutput;
use Kalle\Pdf\Render\PdfOutput;
use LogicException;

abstract class DeferredLengthStreamIndirectObject extends AbstractStreamIndirectObject implements HasDeferredStreamLengthObject
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
        $lengthObject = $this->requireLengthObject();

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->streamDictionary(new ReferenceType($lengthObject))->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);

        $measuringOutput = new MeasuringPdfOutput($output);
        $this->writeStreamContents($measuringOutput);
        $lengthObject->setLength($measuringOutput->writtenBytes());

        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $lengthObject = $this->requireLengthObject();

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->streamDictionary(new ReferenceType($lengthObject))->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);

        $measuringOutput = new MeasuringPdfOutput($output);
        $encryptedOutput = new EncryptingPdfOutput(
            $measuringOutput,
            $objectEncryptor->createStreamEncryptor($this->id),
        );
        $this->writeStreamContents($encryptedOutput);
        $encryptedOutput->finish();
        $lengthObject->setLength($measuringOutput->writtenBytes());

        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function requireLengthObject(): StreamLengthObject
    {
        if ($this->lengthObject === null) {
            throw new LogicException(sprintf(
                'Deferred stream object %s requires a prepared length object before serialization.',
                static::class,
            ));
        }

        return $this->lengthObject;
    }
}
