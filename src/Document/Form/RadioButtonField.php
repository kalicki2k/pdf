<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Kalle\Pdf\Document\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class RadioButtonField extends IndirectObject
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

    public function render(): string
    {
        return $this->renderWithStringEncryptor();
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
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

        return $this->renderDictionaryObject($dictionary, $encryptor);
    }
}
