<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Document\EncryptDictionary;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\IndirectObject;

final class PdfIndirectObjectWriter
{
    public function __construct(private readonly ?StandardObjectEncryptor $objectEncryptor = null)
    {
    }

    public function write(IndirectObject $object, PdfOutput $output): void
    {
        $output->write($this->render($object));
    }

    private function render(IndirectObject $object): string
    {
        $renderedObject = RenderContext::runInObject(
            $object->id,
            static fn (): string => $object->render(),
        );

        if (
            $this->objectEncryptor !== null
            && !$object instanceof EncryptDictionary
        ) {
            return $this->objectEncryptor->encryptStreamObject($renderedObject, $object->id);
        }

        return $renderedObject;
    }
}
