<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Document\EncryptDictionary;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\IndirectObject;

final class PdfObjectSerializer
{
    public function __construct(private readonly ?StandardObjectEncryptor $objectEncryptor = null)
    {
    }

    /**
     * @param iterable<IndirectObject> $objects
     */
    public function writeObjects(iterable $objects, PdfOutput $output): PdfObjectOffsets
    {
        $offsets = [];

        RenderContext::runWith(
            $this->objectEncryptor,
            function () use ($objects, $output, &$offsets): void {
                foreach ($objects as $object) {
                    $offsets[$object->id] = $output->offset();
                    $output->write($this->serializeObject($object));
                }
            },
        );

        return new PdfObjectOffsets($offsets);
    }

    public function serializeObject(IndirectObject $object): string
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
