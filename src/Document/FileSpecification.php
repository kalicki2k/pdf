<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class FileSpecification extends IndirectObject
{
    public function __construct(
        int $id,
        private readonly string $filename,
        private readonly EmbeddedFileStream $embeddedFile,
        private readonly ?string $description = null,
        private readonly ?AssociatedFileRelationship $afRelationship = null,
    ) {
        parent::__construct($id);
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getEmbeddedFile(): EmbeddedFileStream
    {
        return $this->embeddedFile;
    }

    public function hasAfRelationship(): bool
    {
        return $this->afRelationship !== null;
    }

    public function render(): string
    {
        return $this->renderWithStringEncryptor();
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Filespec'),
            'F' => new StringType($this->filename),
            'UF' => new StringType($this->filename),
            'EF' => new DictionaryType([
                'F' => new ReferenceType($this->embeddedFile),
                'UF' => new ReferenceType($this->embeddedFile),
            ]),
        ]);

        if ($this->description !== null && $this->description !== '') {
            $dictionary->add('Desc', new StringType($this->description));
        }

        if ($this->hasAfRelationship()) {
            /** @var AssociatedFileRelationship $afRelationship */
            $afRelationship = $this->afRelationship;
            $dictionary->add('AFRelationship', new NameType($afRelationship->value));
        }

        return $this->renderDictionaryObject($dictionary, $encryptor);
    }
}
