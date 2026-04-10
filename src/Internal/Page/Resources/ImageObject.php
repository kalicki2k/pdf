<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Resources;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Image;
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
        $this->image->write($output, $this->softMask?->getId());
        $output->write('endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->image->writeEncrypted($output, $objectEncryptor, $this->id, $this->softMask?->getId());
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
