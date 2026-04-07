<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Kalle\Pdf\Document\Form\FormFieldFlags;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class ListBoxAnnotation extends IndirectObject implements PageAnnotation
{
    private ?int $structParentId = null;

    /**
     * @param array<string, string> $options
     * @param list<string>|string|null $value
     * @param list<string>|string|null $defaultValue
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
        private readonly string | array | null $value,
        private readonly string $fontResourceName,
        private readonly int $fontSize,
        private readonly ?FormFieldFlags $flags = null,
        private readonly ?Color $textColor = null,
        private readonly string | array | null $defaultValue = null,
        private readonly ?string $tooltip = null,
    ) {
        parent::__construct($id);
    }

    public function withStructParent(int $structParentId): self
    {
        $this->structParentId = $structParentId;

        return $this;
    }

    public function render(): string
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

        if ($this->structParentId !== null) {
            $dictionary->add('StructParent', $this->structParentId);
        }

        if ($this->tooltip !== null && $this->tooltip !== '') {
            $dictionary->add('TU', new StringType($this->tooltip));
        }

        $fieldFlags = ($this->flags ?? new FormFieldFlags())->toPdfFlags(listBox: true);

        if ($fieldFlags > 0) {
            $dictionary->add('Ff', $fieldFlags);
        }

        if ($this->value !== null) {
            $dictionary->add('V', $this->renderChoiceValue($this->value));
        }

        if ($this->defaultValue !== null) {
            $dictionary->add('DV', $this->renderChoiceValue($this->defaultValue));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getRelatedObjects(): array
    {
        return [];
    }

    /**
     * @param list<string>|string $value
     */
    private function renderChoiceValue(string | array $value): StringType | ArrayType
    {
        if (is_string($value)) {
            return new StringType($value);
        }

        return new ArrayType(array_map(
            static fn (string $selectedValue): StringType => new StringType($selectedValue),
            $value,
        ));
    }
}
