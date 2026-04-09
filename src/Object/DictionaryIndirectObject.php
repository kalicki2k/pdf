<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Types\DictionaryType;

abstract class DictionaryIndirectObject extends IndirectObject
{
    public function render(): string
    {
        return $this->renderWithStringEncryptor();
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
    {
        return $this->renderDictionaryObject($this->dictionary(), $encryptor);
    }

    abstract protected function dictionary(): DictionaryType;
}
