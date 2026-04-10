<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Page\Form\FormFieldSignatureAppearanceStream;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\PdfType\StringType;

final class SignatureFieldAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
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
        private readonly ?string $tooltip = null,
        private readonly ?FormFieldSignatureAppearanceStream $appearance = null,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Widget'),
            'FT' => new NameType('Sig'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'Border' => new ArrayType([0, 0, 1]),
            'P' => new ReferenceType($this->page),
            'T' => new StringType($this->name),
        ]);

        $this->addStructParentEntry($dictionary);

        if ($this->tooltip !== null && $this->tooltip !== '') {
            $dictionary->add('TU', new StringType($this->tooltip));
        }

        if ($this->appearance !== null) {
            $dictionary->add('AP', new DictionaryType([
                'N' => new ReferenceType($this->appearance),
            ]));
        }

        return $dictionary;
    }

    public function getRelatedObjects(): array
    {
        return $this->appearance === null ? [] : [$this->appearance];
    }
}
