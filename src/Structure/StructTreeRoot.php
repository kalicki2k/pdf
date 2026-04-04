<?php

declare(strict_types=1);

namespace Kalle\Pdf\Structure;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\RawType;
use Kalle\Pdf\Types\ReferenceType;

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
            $kidReferences[] = new RawType($id . ' 0 R');
        }

        $dictionary = new DictionaryType([
            'Type' => new NameType('StructTreeRoot'),
            'K' => new ArrayType($kidReferences),
        ]);

        if ($this->parentTree !== null) {
            $dictionary->add('ParentTree', new ReferenceType($this->parentTree));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
