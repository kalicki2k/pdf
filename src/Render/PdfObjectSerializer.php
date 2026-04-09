<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\IndirectObject;

final class PdfObjectSerializer
{
    private readonly PdfIndirectObjectWriter $objectWriter;

    public function __construct(private readonly ?StandardObjectEncryptor $objectEncryptor = null)
    {
        $this->objectWriter = new PdfIndirectObjectWriter($objectEncryptor);
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
                    $this->objectWriter->write($object, $output);
                }
            },
        );

        return new PdfObjectOffsets($offsets);
    }
}
