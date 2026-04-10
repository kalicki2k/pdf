<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Resources;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;

class ImageObject extends IndirectObject implements EncryptableIndirectObject
{
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

    protected function writeObject(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->image->writeDictionary($output, $this->softMask?->getId(), $this->image->streamLength());
        $output->write('stream' . PHP_EOL);
        $this->image->writeStreamContents($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL);
        $output->write('endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->image->writeDictionary(
            $output,
            $this->softMask?->getId(),
            $this->image->encryptedStreamLength($objectEncryptor),
        );
        $output->write('stream' . PHP_EOL);
        $this->image->writeEncryptedStreamContents($output, $objectEncryptor, $this->id);
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
}
