<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Resources;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\HasDeferredStreamLengthObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Object\StreamLengthObject;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\EncryptingPdfOutput;
use Kalle\Pdf\Render\MeasuringPdfOutput;
use Kalle\Pdf\Render\PdfOutput;
use LogicException;

class ImageObject extends IndirectObject implements EncryptableIndirectObject, HasDeferredStreamLengthObject
{
    private ?StreamLengthObject $lengthObject = null;

    public function __construct(
        int $id,
        private readonly Image $image,
        private readonly ?self $softMask = null,
    ) {
        parent::__construct($id);
    }

    public function getId(): int
    {
        return $this->id;
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

    protected function writeObject(PdfOutput $output): void
    {
        $lengthObject = $this->requireLengthObject();

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->image->dictionary(new ReferenceType($lengthObject), $this->softMask?->getId())->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);

        $measuringOutput = new MeasuringPdfOutput($output);
        $this->image->writeStreamContents($measuringOutput);
        $lengthObject->setLength($measuringOutput->writtenBytes());

        $output->write(PHP_EOL . 'endstream' . PHP_EOL);
        $output->write('endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $lengthObject = $this->requireLengthObject();

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->image->dictionary(new ReferenceType($lengthObject), $this->softMask?->getId())->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);

        $measuringOutput = new MeasuringPdfOutput($output);
        $encryptedOutput = new EncryptingPdfOutput(
            $measuringOutput,
            $objectEncryptor->createStreamEncryptor($this->id),
        );
        $this->image->writeStreamContents($encryptedOutput);
        $encryptedOutput->finish();
        $lengthObject->setLength($measuringOutput->writtenBytes());

        $output->write(PHP_EOL . 'endstream' . PHP_EOL);
        $output->write('endobj' . PHP_EOL);
    }

    /**
     * @return list<self>
     */
    public function getRelatedObjects(): array
    {
        if ($this->softMask === null) {
            return [$this];
        }

        return [$this, ...$this->softMask->getRelatedObjects()];
    }

    private function requireLengthObject(): StreamLengthObject
    {
        if ($this->lengthObject === null) {
            throw new LogicException(sprintf(
                'Image object %s requires a prepared length object before serialization.',
                static::class,
            ));
        }

        return $this->lengthObject;
    }
}
