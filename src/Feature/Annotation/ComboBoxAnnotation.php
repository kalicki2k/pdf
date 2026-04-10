<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Annotation;

use Kalle\Pdf\Feature\Form\FormFieldFlags;
use Kalle\Pdf\Feature\Form\FormFieldTextAppearanceStream;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class ComboBoxAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    /**
     * @param array<string, string> $options
     */
    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly string $name,
        private readonly array $options,
        private readonly ?string $value,
        private readonly string $fontResourceName,
        private readonly int $fontSize,
        private readonly ?FormFieldFlags $flags = null,
        private readonly ?Color $textColor = null,
        private readonly ?string $defaultValue = null,
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
            'FT' => new NameType('Ch'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'Border' => new ArrayType([0, 0, 1]),
            'P' => new ReferenceType($this->page),
            'T' => new StringType($this->name),
            'DA' => new StringType($defaultAppearance),
            'Opt' => new ArrayType(array_map(
                static fn (string $exportValue, string $label): ArrayType => new ArrayType([
                    new StringType($exportValue),
                    new StringType($label),
                ]),
                array_keys($this->options),
                array_values($this->options),
            )),
        ]);

        $this->addStructParentEntry($dictionary);

        if ($this->tooltip !== null && $this->tooltip !== '') {
            $dictionary->add('TU', new StringType($this->tooltip));
        }

        $fieldFlags = ($this->flags ?? new FormFieldFlags())->toPdfFlags(combo: true);

        if ($fieldFlags > 0) {
            $dictionary->add('Ff', $fieldFlags);
        }

        if ($this->value !== null) {
            $dictionary->add('V', new StringType($this->value));
        }

        if ($this->defaultValue !== null) {
            $dictionary->add('DV', new StringType($this->defaultValue));
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
