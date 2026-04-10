<?php

declare(strict_types=1);

namespace Kalle\Pdf\TaggedPdf;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\RawType;
use Kalle\Pdf\PdfType\ReferenceType;

final class StructTreeRoot extends DictionaryIndirectObject
{
    /** @var int[]  */
    private array $kids = [];

    public ?ParentTree $parentTree = null;

    public function addKid(int $id): self
    {
        $this->kids[] = $id;

        return $this;
    }

    protected function dictionary(): DictionaryType
    {
        $kidReferences = [];

        foreach ($this->kids as $id) {
            $kidReferences[] = new RawType($id . ' 0 R');
        }

        $dictionary = new DictionaryType([
            'Type' => new NameType('StructTreeRoot'),
            'K' => new ArrayType($kidReferences),
        ]);

        if ($this->parentTree !== null) {
            $dictionary->add('ParentTree', new ReferenceType($this->parentTree));
        }

        return $dictionary;
    }
}
