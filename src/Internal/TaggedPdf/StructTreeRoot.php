<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\TaggedPdf;

use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\RawType;
use Kalle\Pdf\Types\ReferenceType;

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
