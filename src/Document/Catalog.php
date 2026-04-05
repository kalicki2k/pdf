<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\BooleanType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class Catalog extends IndirectObject
{
    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Catalog'),
            'Pages' => new ReferenceType($this->document->pages),
        ]);

        if ($this->document->outlineRoot !== null) {
            $dictionary->add('Outlines', new ReferenceType($this->document->outlineRoot));
            $dictionary->add('PageMode', new NameType('UseOutlines'));
        }

        if ($this->document->getDestinations() !== []) {
            $destinations = new DictionaryType([]);

            foreach ($this->document->getDestinations() as $name => $page) {
                $destinations->add($name, new \Kalle\Pdf\Types\ArrayType([
                    new ReferenceType($page),
                    new NameType('Fit'),
                ]));
            }

            $dictionary->add('Dests', $destinations);
        }

        if ($this->document->acroForm !== null) {
            $dictionary->add('AcroForm', new ReferenceType($this->document->acroForm));
        }

        if ($this->document->version >= 1.4 && $this->document->structTreeRoot !== null) {
            $dictionary->add('MarkInfo', new DictionaryType([
                'Marked' => new BooleanType(true),
            ]));
            $dictionary->add('Lang', new StringType($this->document->language ?? ''));
            $dictionary->add('StructTreeRoot', new ReferenceType($this->document->structTreeRoot));
        }

        return "$this->id 0 obj" . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
