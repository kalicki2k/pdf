<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Document\EncryptDictionary;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
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
                    $this->writeObject($object, $output);
                }
            },
        );

        return new PdfObjectOffsets($offsets);
    }

    private function writeObject(IndirectObject $object, PdfOutput $output): void
    {
        $objectEncryptor = $this->objectEncryptor;

        RenderContext::runInObject(
            $object->id,
            static function () use ($object, $output, $objectEncryptor): void {
                if (
                    $objectEncryptor !== null
                    && !$object instanceof EncryptDictionary
                    && $object instanceof EncryptableIndirectObject
                ) {
                    $object->writeEncrypted($output, $objectEncryptor);

                    return;
                }

                $object->write($output);
            },
        );
    }
}
