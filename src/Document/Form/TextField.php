<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use InvalidArgumentException;

final readonly class TextField extends WidgetFormField
{
    public function __construct(
        string $name,
        int $pageNumber,
        float $x,
        float $y,
        float $width,
        float $height,
        public ?string $value = null,
        ?string $alternativeName = null,
        public ?string $defaultValue = null,
        public float $fontSize = 12.0,
        public bool $multiline = false,
    ) {
        parent::__construct($name, $pageNumber, $x, $y, $width, $height, $alternativeName);

        if ($this->fontSize <= 0.0) {
            throw new InvalidArgumentException('Text field font size must be greater than zero.');
        }
    }

    public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string {
        $entries = [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Tx',
            '/DA ' . $this->pdfString('/Helv ' . $this->formatNumber($this->fontSize) . ' Tf 0 g'),
        ];

        if ($this->multiline) {
            $entries[] = '/Ff 4096';
        }

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
