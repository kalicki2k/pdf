<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\BooleanType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class TextAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    private const int PRINT_FLAG = 4;

    private ?TextAnnotationAppearanceStream $appearance = null;
    private ?PopupAnnotation $popup = null;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly string $contents,
        private readonly ?string $title = null,
        private readonly string $icon = 'Note',
        private readonly bool $open = false,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Text'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'P' => new ReferenceType($this->page),
            'Contents' => new StringType($this->contents),
            'Name' => new NameType($this->icon),
            'Open' => new BooleanType($this->open),
        ]);

        if ($this->page->getDocument()->getProfile()->requiresPrintableAnnotations()) {
            $dictionary->add('F', self::PRINT_FLAG);
        }

        $this->addStructParentEntry($dictionary);

        if ($this->title !== null && $this->title !== '') {
            $dictionary->add('T', new StringType($this->title));
        }

        if ($this->appearance !== null) {
            $dictionary->add('AP', new DictionaryType([
                'N' => new ReferenceType($this->appearance),
            ]));
        }

        if ($this->popup !== null) {
            $dictionary->add('Popup', new ReferenceType($this->popup));
        }

        return $dictionary;
    }

    public function getRelatedObjects(): array
    {
        return array_values(array_filter([
            $this->popup,
            $this->appearance,
        ]));
    }

    public function withPopup(PopupAnnotation $popup): self
    {
        $this->popup = $popup;

        return $this;
    }

    public function withAppearance(TextAnnotationAppearanceStream $appearance): self
    {
        $this->appearance = $appearance;

        return $this;
    }
}
