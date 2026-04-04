<?php

declare(strict_types=1);

namespace Kalle\Pdf\Structure;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\RawType;

final class ParentTree extends IndirectObject
{
    /** @var array<int, list<StructElem>> */
    private array $structElems = [];

    public function add(int $structParentId, StructElem $structElem): self
    {
        $this->structElems[$structParentId] ??= [];
        $this->structElems[$structParentId][] = $structElem;

        return $this;
    }

    public function render(): string
    {
        $nums = [];
        ksort($this->structElems);

        foreach ($this->structElems as $structParentId => $structElems) {
            $nums[] = $structParentId;
            $nums[] = new ArrayType(array_map(
                static fn (StructElem $structElem): RawType => new RawType($structElem->id . ' 0 R'),
                $structElems,
            ));
        }

        $dictionary = new DictionaryType([
            'Nums' => new ArrayType($nums),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
