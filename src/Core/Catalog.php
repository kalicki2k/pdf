<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use Kalle\Pdf\Types\BooleanValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;
use Kalle\Pdf\Types\StringValue;

final class Catalog extends IndirectObject
{
    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);
    }

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Catalog'),
            'Pages' => new Reference($this->document->pages),
        ]);

        if ($this->document->version >= 1.4) {
            $dictionary->add('MarkInfo', new Dictionary([
                'Marked' => new BooleanValue(true),
            ]));
            $dictionary->add('Lang', new StringValue($this->document->language ?? ''));
            $dictionary->add('StructTreeRoot', new Reference($this->document->structTreeRoot));
        }

        return "$this->id 0 obj" . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
