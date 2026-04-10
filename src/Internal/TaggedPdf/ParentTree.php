<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\TaggedPdf;

use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\ReferenceType;

final class ParentTree extends DictionaryIndirectObject
{
    /** @var array<int, list<StructElem>> */
    private array $markedContentStructElems = [];
    /** @var array<int, StructElem> */
    private array $objectStructElems = [];

    public function add(int $structParentId, StructElem $structElem): self
    {
        $this->markedContentStructElems[$structParentId] ??= [];
        $this->markedContentStructElems[$structParentId][] = $structElem;

        return $this;
    }

    public function addObject(int $structParentId, StructElem $structElem): self
    {
        $this->objectStructElems[$structParentId] = $structElem;

        return $this;
    }

    protected function dictionary(): DictionaryType
    {
        $nums = [];
        $entries = [];

        foreach ($this->markedContentStructElems as $structParentId => $structElems) {
            $entries[$structParentId] = new ArrayType(array_map(
                static fn (StructElem $structElem): ReferenceType => new ReferenceType($structElem),
                $structElems,
            ));
        }

        foreach ($this->objectStructElems as $structParentId => $structElem) {
            $entries[$structParentId] = new ReferenceType($structElem);
        }

        ksort($entries);

        foreach ($entries as $structParentId => $entry) {
            $nums[] = $structParentId;
            $nums[] = $entry;
        }

        $dictionary = new DictionaryType([
            'Nums' => new ArrayType($nums),
        ]);

        return $dictionary;
    }
}
