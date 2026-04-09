<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Document\EncryptDictionary;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
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

        if ($object instanceof EncryptableIndirectObject) {
            $this->writeEncryptedObject($object, $output);

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

        return $this->objectEncryptor()->encryptStreamObject($renderedObject, $object->id);
    }

    private function writeEncryptedObject(IndirectObject $object, PdfOutput $output): void
    {
        if (!$object instanceof EncryptableIndirectObject) {
            throw new \LogicException('Encrypted object writing requires an encryptable indirect object.');
        }

        $objectEncryptor = $this->objectEncryptor();

        RenderContext::runInObject(
            $object->id,
            static function () use ($object, $output, $objectEncryptor): void {
                $object->writeEncrypted($output, $objectEncryptor);
            },
        );
    }

    private function objectEncryptor(): StandardObjectEncryptor
    {
        $objectEncryptor = $this->objectEncryptor;

        if ($objectEncryptor === null) {
            throw new \LogicException('Encrypted object rendering requires an initialized object encryptor.');
        }

        return $objectEncryptor;
    }
}
