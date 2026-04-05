<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\BooleanType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class PopupAnnotation extends IndirectObject implements PageAnnotation
{
    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly IndirectObject $parent,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly bool $open = false,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Popup'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'P' => new ReferenceType($this->page),
            'Parent' => new ReferenceType($this->parent),
            'Open' => new BooleanType($this->open),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getRelatedObjects(): array
    {
        return [];
    }
}
