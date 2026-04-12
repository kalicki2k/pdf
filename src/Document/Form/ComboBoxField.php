<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function array_map;

use function array_values;
use function implode;

use InvalidArgumentException;

final readonly class ComboBoxField extends WidgetFormField
{
    /**
     * @param array<string, string> $options
     */
    public function __construct(
        string $name,
        int $pageNumber,
        float $x,
        float $y,
        float $width,
        float $height,
        public array $options,
        public ?string $value = null,
        ?string $alternativeName = null,
        public ?string $defaultValue = null,
        public float $fontSize = 12.0,
    ) {
        parent::__construct($name, $pageNumber, $x, $y, $width, $height, $alternativeName);

        if ($this->fontSize <= 0.0) {
            throw new InvalidArgumentException('Combo box font size must be greater than zero.');
        }

        if ($this->options === []) {
            throw new InvalidArgumentException('Combo box options must not be empty.');
        }

        if ($this->value !== null && !isset($this->options[$this->value])) {
            throw new InvalidArgumentException(sprintf(
                'Combo box value "%s" is not present in the available options.',
                $this->value,
            ));
        }

        if ($this->defaultValue !== null && !isset($this->options[$this->defaultValue])) {
            throw new InvalidArgumentException(sprintf(
                'Combo box default value "%s" is not present in the available options.',
                $this->defaultValue,
            ));
        }
    }

    public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string {
        $entries = [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Ch',
            '/Ff 131072',
            '/DA ' . $this->pdfString('/Helv ' . $this->formatNumber($this->fontSize) . ' Tf 0 g'),
            '/Opt [' . implode(' ', array_map(
                fn (string $exportValue, string $label): string => '[' . $this->pdfString($exportValue) . ' ' . $this->pdfString($label) . ']',
                array_keys($this->options),
                array_values($this->options),
            )) . ']',
        ];

        if ($this->value !== null) {
            $entries[] = '/V ' . $this->pdfString($this->value);
        }

        if ($this->defaultValue !== null) {
            $entries[] = '/DV ' . $this->pdfString($this->defaultValue);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function needsDefaultTextResources(): bool
    {
        return true;
    }
}
