<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

use Kalle\Pdf\Internal\Action\ButtonAction;
use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Internal\Page\Form\FormFieldTextAppearanceStream;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\PdfType\ArrayType;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;
use Kalle\Pdf\Internal\PdfType\ReferenceType;
use Kalle\Pdf\Internal\PdfType\StringType;
use Kalle\Pdf\Internal\Style\Color;

final class PushButtonAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
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
        private readonly string $label,
        private readonly string $fontResourceName,
        private readonly int $fontSize,
        private readonly ?Color $textColor = null,
        private readonly ?ButtonAction $action = null,
        private readonly ?string $tooltip = null,
        private readonly ?FormFieldTextAppearanceStream $appearance = null,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $defaultAppearance = sprintf(
            '/%s %d Tf %s',
            $this->fontResourceName,
            $this->fontSize,
            $this->textColor?->renderNonStrokingOperator() ?? '0 g',
        );

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
            'Border' => new ArrayType([0, 0, 1]),
            'P' => new ReferenceType($this->page),
            'T' => new StringType($this->name),
            'Ff' => 65536,
            'DA' => new StringType($defaultAppearance),
            'MK' => new DictionaryType([
                'CA' => new StringType($this->label),
            ]),
        ]);

        $this->addStructParentEntry($dictionary);

        if ($this->tooltip !== null && $this->tooltip !== '') {
            $dictionary->add('TU', new StringType($this->tooltip));
        }

        if ($this->action !== null) {
            $dictionary->add('A', $this->action->toPdfDictionary());
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
