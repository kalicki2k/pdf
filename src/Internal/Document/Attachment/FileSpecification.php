<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Attachment;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\PdfType\StringType;

final class FileSpecification extends DictionaryIndirectObject
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

    protected function dictionary(): DictionaryType
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

        return $dictionary;
    }
}
