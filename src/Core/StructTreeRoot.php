<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\RawValue;

final class StructTreeRoot extends IndirectObject
{
    /** @var int[]  */
    private array $kids = [];

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

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
