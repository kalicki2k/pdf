<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function count;
use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Writer\IndirectObject;

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

    public function relatedObjectCount(): int
    {
        return 1;
    }

    public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string {
        if (count($relatedObjectIds) !== 1) {
            throw new InvalidArgumentException('Text fields require one appearance object ID.');
        }

        $entries = [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Tx',
            '/DA ' . $this->pdfString('/Helv ' . $this->formatNumber($this->fontSize) . ' Tf 0 g'),
            '/AP << /N ' . $relatedObjectIds[0] . ' 0 R >>',
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

    public function relatedObjects(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): array {
        if (count($relatedObjectIds) !== 1) {
            throw new InvalidArgumentException('Text fields require one appearance object ID.');
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
}
