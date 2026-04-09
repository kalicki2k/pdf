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
        $buffer = new StringPdfOutput();

        RenderContext::runInObject(
            $object->id,
            static function () use ($object, $buffer): void {
                $object->write($buffer);
            },
        );

        $renderedObject = $buffer->contents();

        if (
            $this->objectEncryptor !== null
            && !$object instanceof EncryptDictionary
        ) {
            return $this->objectEncryptor->encryptStreamObject($renderedObject, $object->id);
        }

        return $renderedObject;
    }
}
