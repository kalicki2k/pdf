<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

use Kalle\Pdf\Internal\Page\Form\CheckboxAppearanceStream;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Page;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\PdfType\StringType;

final class CheckboxAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly string $name,
        private readonly bool $checked,
        private readonly CheckboxAppearanceStream $offAppearance,
        private readonly CheckboxAppearanceStream $onAppearance,
        private readonly ?string $tooltip = null,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $state = $this->checked ? 'Yes' : 'Off';

        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Widget'),
            'FT' => new NameType('Btn'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'Border' => new ArrayType([0, 0, 0]),
            'P' => new ReferenceType($this->page),
            'T' => new StringType($this->name),
            'V' => new NameType($state),
            'AS' => new NameType($state),
            'AP' => new DictionaryType([
                'N' => new DictionaryType([
                    'Off' => new ReferenceType($this->offAppearance),
                    'Yes' => new ReferenceType($this->onAppearance),
                ]),
            ]),
        ]);

        $this->addStructParentEntry($dictionary);

        if ($this->tooltip !== null && $this->tooltip !== '') {
            $dictionary->add('TU', new StringType($this->tooltip));
        }

        return $dictionary;
    }

    public function getRelatedObjects(): array
    {
        return [$this->offAppearance, $this->onAppearance];
    }
}
