<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class OutlineRoot extends IndirectObject
{
    /** @var list<OutlineItem> */
    private array $items = [];

    public function addItem(OutlineItem $item): void
    {
        $lastItem = $this->items[array_key_last($this->items)] ?? null;

        if ($lastItem instanceof OutlineItem) {
            $lastItem->setNext($item);
            $item->setPrev($lastItem);
        }

        $this->items[] = $item;
    }

    /**
     * @return list<OutlineItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Outlines'),
            'Count' => count($this->items),
        ]);

        $firstItem = $this->items[0] ?? null;
        $lastItem = $this->items[array_key_last($this->items)] ?? null;

        if ($firstItem instanceof OutlineItem) {
            $dictionary->add('First', new ReferenceType($firstItem));
        }

        if ($lastItem instanceof OutlineItem) {
            $dictionary->add('Last', new ReferenceType($lastItem));
        }

        return "$this->id 0 obj" . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
