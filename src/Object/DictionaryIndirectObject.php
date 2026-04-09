<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Types\DictionaryType;

abstract class DictionaryIndirectObject extends IndirectObject
{
    protected function writeObject(\Kalle\Pdf\Render\PdfOutput $output): void
    {
        $this->writeDictionaryObject($output, $this->dictionary());
    }

    protected function writeObjectWithStringEncryptor(
        \Kalle\Pdf\Render\PdfOutput $output,
        ObjectStringEncryptor $encryptor,
    ): void {
        $this->writeDictionaryObject($output, $this->dictionary(), $encryptor);
    }

    abstract protected function dictionary(): DictionaryType;
}
