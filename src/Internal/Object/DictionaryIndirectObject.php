<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Object;

use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;

abstract class DictionaryIndirectObject extends IndirectObject
{
    protected function writeObject(PdfOutput $output): void
    {
        $this->writeDictionaryObject($output, $this->dictionary());
    }

    protected function writeObjectWithStringEncryptor(
        PdfOutput $output,
        ObjectStringEncryptor $encryptor,
    ): void {
        $this->writeDictionaryObject($output, $this->dictionary(), $encryptor);
    }

    abstract protected function dictionary(): DictionaryType;
}
