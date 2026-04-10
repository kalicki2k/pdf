<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\PdfOutput;

abstract class AbstractStreamIndirectObject extends IndirectObject implements EncryptableIndirectObject
{
    /**
     * @param list<string> $lines
     */
    protected function writeLines(PdfOutput $output, array $lines): void
    {
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $output->write(PHP_EOL);
            }

            $output->write($line);
        }
    }

    abstract protected function streamDictionary(int | ReferenceType $length): DictionaryType;

    abstract protected function writeStreamContents(PdfOutput $output): void;

    abstract public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void;
}
