<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function implode;

use InvalidArgumentException;

final readonly class PushButtonField extends WidgetFormField
{
    public function __construct(
        string $name,
        int $pageNumber,
        float $x,
        float $y,
        float $width,
        float $height,
        public string $label,
        ?string $alternativeName = null,
        public ?string $url = null,
        public float $fontSize = 12.0,
    ) {
        parent::__construct($name, $pageNumber, $x, $y, $width, $height, $alternativeName);

        if ($this->label === '') {
            throw new InvalidArgumentException('Push button label must not be empty.');
        }

        if ($this->fontSize <= 0.0) {
            throw new InvalidArgumentException('Push button font size must be greater than zero.');
        }

        if ($this->url === '') {
            throw new InvalidArgumentException('Push button URL must not be empty.');
        }
    }

    public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string {
        $entries = [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Btn',
            '/Ff 65536',
            '/DA ' . $this->pdfString('/Helv ' . $this->formatNumber($this->fontSize) . ' Tf 0 g'),
            '/MK << /CA ' . $this->pdfString($this->label) . ' >>',
        ];

        if ($this->url !== null) {
            $entries[] = '/A << /S /URI /URI ' . $this->pdfString($this->url) . ' >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function needsDefaultTextResources(): bool
    {
        return true;
    }
}
