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
        if ($this->writesDirectly($object)) {
            RenderContext::runInObject(
                $object->id,
                static function () use ($object, $output): void {
                    $object->write($output);
                },
            );

            return;
        }

        $output->write($this->renderEncryptedObject($object));
    }

    private function writesDirectly(IndirectObject $object): bool
    {
        return $this->objectEncryptor === null || $object instanceof EncryptDictionary;
    }

    private function renderEncryptedObject(IndirectObject $object): string
    {
        $buffer = new StringPdfOutput();

        RenderContext::runInObject(
            $object->id,
            static function () use ($object, $buffer): void {
                $object->write($buffer);
            },
        );

        $renderedObject = $buffer->contents();

        $objectEncryptor = $this->objectEncryptor;

        if ($objectEncryptor === null) {
            throw new \LogicException('Encrypted object rendering requires an initialized object encryptor.');
        }

        return $objectEncryptor->encryptStreamObject($renderedObject, $object->id);
    }
}
