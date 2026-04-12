<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function array_map;
use function array_values;
use function count;
use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Writer\IndirectObject;

use function is_string;

final readonly class ListBoxField extends WidgetFormField
{
    /**
     * @param array<string, string> $options
     * @param list<string>|string|null $value
     * @param list<string>|string|null $defaultValue
     */
    public function __construct(
        string $name,
        int $pageNumber,
        float $x,
        float $y,
        float $width,
        float $height,
        public array $options,
        public string | array | null $value = null,
        ?string $alternativeName = null,
        public string | array | null $defaultValue = null,
        public float $fontSize = 12.0,
    ) {
        parent::__construct($name, $pageNumber, $x, $y, $width, $height, $alternativeName);

        if ($this->fontSize <= 0.0) {
            throw new InvalidArgumentException('List box font size must be greater than zero.');
        }

        if ($this->options === []) {
            throw new InvalidArgumentException('List box options must not be empty.');
        }

        $this->assertChoiceValuesExist($this->value, 'value');
        $this->assertChoiceValuesExist($this->defaultValue, 'default value');
    }

    public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string {
        if (count($relatedObjectIds) !== 1) {
            throw new InvalidArgumentException('List boxes require one appearance object ID.');
        }

        $entries = [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Ch',
            '/DA ' . $this->pdfString('/Helv ' . $this->formatNumber($this->fontSize) . ' Tf 0 g'),
            '/AP << /N ' . $relatedObjectIds[0] . ' 0 R >>',
            '/Opt [' . implode(' ', array_map(
                fn (string $exportValue, string $label): string => '[' . $this->pdfString($exportValue) . ' ' . $this->pdfString($label) . ']',
                array_keys($this->options),
                array_values($this->options),
            )) . ']',
        ];

        if ($this->isMultiSelect($this->value) || $this->isMultiSelect($this->defaultValue)) {
            $entries[] = '/Ff 2097152';
        }

        if ($this->value !== null) {
            $entries[] = '/V ' . $this->renderChoiceValue($this->value);
        }

        if ($this->defaultValue !== null) {
            $entries[] = '/DV ' . $this->renderChoiceValue($this->defaultValue);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function relatedObjectCount(): int
    {
        return 1;
    }

    public function relatedObjects(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): array {
        if (count($relatedObjectIds) !== 1) {
            throw new InvalidArgumentException('List boxes require one appearance object ID.');
        }

        return [
            IndirectObject::stream(
                $relatedObjectIds[0],
                $this->appearanceStreamDictionaryContents(),
                $this->appearanceStreamContents(),
            ),
        ];
    }

    public function needsDefaultTextResources(): bool
    {
        return true;
    }

    private function appearanceStreamDictionaryContents(): string
    {
        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($this->width)
            . ' '
            . $this->formatNumber($this->height)
            . '] /Resources << >> /Length 0 >>';
    }

    private function appearanceStreamContents(): string
    {
        return implode("\n", [
            '1 g',
            '0 G',
            '1 w',
            '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
            'B',
        ]);
    }

    /**
     * @param list<string>|string|null $value
     */
    private function assertChoiceValuesExist(string | array | null $value, string $label): void
    {
        if ($value === null) {
            return;
        }

        $values = is_string($value) ? [$value] : $value;

        foreach ($values as $selectedValue) {
            if (isset($this->options[$selectedValue])) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'List box %s "%s" is not present in the available options.',
                $label,
                $selectedValue,
            ));
        }
    }

    /**
     * @param list<string>|string|null $value
     */
    private function isMultiSelect(string | array | null $value): bool
    {
        return is_array($value);
    }

    /**
     * @param list<string>|string $value
     */
    private function renderChoiceValue(string | array $value): string
    {
        if (is_string($value)) {
            return $this->pdfString($value);
        }

        return '[' . implode(' ', array_map(
            fn (string $selectedValue): string => $this->pdfString($selectedValue),
            $value,
        )) . ']';
    }
}
