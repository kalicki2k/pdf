<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Document\Form\RadioButtonField;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Page;
use Kalle\Pdf\Page\Form\RadioButtonAppearanceStream;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;

final class RadioButtonWidgetAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
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

    protected function dictionary(): DictionaryType
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

        return $dictionary;
    }

    public function getRelatedObjects(): array
    {
        return [$this->offAppearance, $this->onAppearance];
    }
}
