<?php

declare(strict_types=1);

namespace Kalle\Pdf\Structure;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\RawValue;
use Kalle\Pdf\Types\Reference;

final class StructTreeRoot extends IndirectObject
{
    /** @var int[]  */
    private array $kids = [];

    public ?ParentTree $parentTree = null;

    public function addKid(int $id): self
    {
        $this->kids[] = $id;

        return $this;
    }

    public function render(): string
    {
        $kidReferences = [];

        foreach ($this->kids as $id) {
            $kidReferences[] = new RawValue($id . ' 0 R');
        }

        $dictionary = new Dictionary([
            'Type' => new Name('StructTreeRoot'),
            'K' => new ArrayValue($kidReferences),
        ]);

        if ($this->parentTree !== null) {
            $dictionary->add('ParentTree', new Reference($this->parentTree));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
