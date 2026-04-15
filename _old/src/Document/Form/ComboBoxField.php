<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function array_map;
use function array_values;
use function count;
use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Writer\IndirectObject;
use Override;

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
        if (count($relatedObjectIds) !== 1) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'Combo boxes require one appearance object ID.',
            );
        }

        $entries = [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Ch',
            '/Ff 131072',
            '/DA ' . $this->pdfString(
                $context->defaultTextFont !== null
                    ? $this->pdfAFieldDa($context, $this->fontSize)
                    : '/Helv ' . $this->formatNumber($this->fontSize) . ' Tf 0 g',
            ),
            '/AP << /N ' . $relatedObjectIds[0] . ' 0 R >>',
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

    #[Override]
    public function relatedObjectCount(): int
    {
        return 1;
    }

    #[Override]
    public function relatedObjects(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): array {
        if (count($relatedObjectIds) !== 1) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'Combo boxes require one appearance object ID.',
            );
        }

        return [
            IndirectObject::stream(
                $relatedObjectIds[0],
                $this->appearanceStreamDictionaryContents($context),
                $this->appearanceStreamContents($context),
            ),
        ];
    }

    #[Override]
    public function needsDefaultTextResources(): bool
    {
        return true;
    }

    private function appearanceStreamDictionaryContents(FormFieldRenderContext $context): string
    {
        if ($context->defaultTextFont !== null) {
            return $this->renderPdfAAppearanceDictionary($context, $this->width, $this->height);
        }

        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($this->width)
            . ' '
            . $this->formatNumber($this->height)
            . '] /Resources << >> /Length 0 >>';
    }

    private function appearanceStreamContents(FormFieldRenderContext $context): string
    {
        if ($context->defaultTextFont !== null) {
            $selected = $this->value ?? array_key_first($this->options);
            $label = $this->options[$selected] ?? '';

            return implode("\n", [
                '1 g',
                '0 G',
                '1 w',
                '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
                'B',
                'BT',
                '/' . $context->requiresDefaultTextFontAlias() . ' ' . $this->formatNumber($this->fontSize) . ' Tf',
                '0 g',
                '2 ' . $this->formatNumber(max(2.0, ($this->height - $this->fontSize) / 2)) . ' Td',
                '<' . $this->pdfAEncodedTextHex($context, $label) . '> Tj',
                'ET',
            ]);
        }

        return implode("\n", [
            '1 g',
            '0 G',
            '1 w',
            '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
            'B',
        ]);
    }
}
