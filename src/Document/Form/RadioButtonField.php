<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Page\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\PdfType\StringType;

final class RadioButtonField extends DictionaryIndirectObject
{
    /** @var list<RadioButtonWidgetAnnotation> */
    private array $widgets = [];
    private ?string $selectedValue = null;
    private ?string $tooltip = null;

    public function __construct(
        int $id,
        private readonly string $name,
    ) {
        parent::__construct($id);
    }

    public function addWidget(RadioButtonWidgetAnnotation $widget, string $exportValue, bool $checked): self
    {
        $this->widgets[] = $widget;

        if ($checked) {
            $this->selectedValue = $exportValue;
        }

        return $this;
    }

    public function withTooltip(string $tooltip): self
    {
        if ($tooltip !== '' && $this->tooltip === null) {
            $this->tooltip = $tooltip;
        }

        return $this;
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'FT' => new NameType('Btn'),
            'T' => new StringType($this->name),
            'Ff' => 49152,
            'Kids' => new ArrayType(array_map(
                static fn (RadioButtonWidgetAnnotation $widget): ReferenceType => new ReferenceType($widget),
                $this->widgets,
            )),
        ]);

        if ($this->selectedValue !== null) {
            $dictionary->add('V', new NameType($this->selectedValue));
        }

        if ($this->tooltip !== null && $this->tooltip !== '') {
            $dictionary->add('TU', new StringType($this->tooltip));
        }

        return $dictionary;
    }
}
