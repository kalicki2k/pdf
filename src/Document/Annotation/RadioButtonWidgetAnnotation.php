<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Kalle\Pdf\Document\Form\RadioButtonAppearanceStream;
use Kalle\Pdf\Document\Form\RadioButtonField;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class RadioButtonWidgetAnnotation extends IndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly RadioButtonField $parent,
        private readonly float $x,
        private readonly float $y,
        private readonly float $size,
        private readonly string $exportValue,
        private readonly bool $checked,
        private readonly RadioButtonAppearanceStream $offAppearance,
        private readonly RadioButtonAppearanceStream $onAppearance,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $state = $this->checked ? $this->exportValue : 'Off';

        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Widget'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->size,
                $this->y + $this->size,
            ]),
            'Border' => new ArrayType([0, 0, 0]),
            'P' => new ReferenceType($this->page),
            'Parent' => new ReferenceType($this->parent),
            'AS' => new NameType($state),
            'AP' => new DictionaryType([
                'N' => new DictionaryType([
                    'Off' => new ReferenceType($this->offAppearance),
                    $this->exportValue => new ReferenceType($this->onAppearance),
                ]),
            ]),
        ]);

        $this->addStructParentEntry($dictionary);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getRelatedObjects(): array
    {
        return [$this->offAppearance, $this->onAppearance];
    }
}
