<?php

declare(strict_types=1);

namespace Kalle\Pdf\Structure;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\RawValue;

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
            $nums[] = new ArrayValue(array_map(
                static fn (StructElem $structElem): RawValue => new RawValue($structElem->id . ' 0 R'),
                $structElems,
            ));
        }

        $dictionary = new Dictionary([
            'Nums' => new ArrayValue($nums),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
