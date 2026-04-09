<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

final class OptionalContentGroup extends IndirectObject
{
    public function __construct(
        int $id,
        private readonly string $name,
        private readonly bool $visibleByDefault = true,
    ) {
        parent::__construct($id);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isVisibleByDefault(): bool
    {
        return $this->visibleByDefault;
    }

    public function render(): string
    {
        return $this->renderWithStringEncryptor();
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('OCG'),
            'Name' => new StringType($this->name),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render($encryptor) . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
