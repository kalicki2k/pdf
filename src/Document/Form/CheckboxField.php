<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function count;
use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Writer\IndirectObject;
use Override;

final readonly class CheckboxField extends WidgetFormField
{
    public function __construct(
        string $name,
        int $pageNumber,
        float $x,
        float $y,
        float $size,
        public bool $checked = false,
        ?string $alternativeName = null,
    ) {
        parent::__construct($name, $pageNumber, $x, $y, $size, $size, $alternativeName);
    }

    #[Override]
    public function relatedObjectCount(): int
    {
        return 2;
    }

    public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string {
        if (count($relatedObjectIds) !== 2) {
            throw new InvalidArgumentException('Checkbox fields require two appearance object IDs.');
        }

        $state = $this->checked ? 'Yes' : 'Off';
        [$offAppearanceObjectId, $onAppearanceObjectId] = $relatedObjectIds;

        return '<< ' . implode(' ', [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Btn',
            '/V /' . $state,
            '/AS /' . $state,
            '/AP << /N << /Off ' . $offAppearanceObjectId . ' 0 R /Yes ' . $onAppearanceObjectId . ' 0 R >> >>',
        ]) . ' >>';
    }

    #[Override]
    public function relatedObjects(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): array {
        if (count($relatedObjectIds) !== 2) {
            throw new InvalidArgumentException('Checkbox fields require two appearance object IDs.');
        }

        [$offAppearanceObjectId, $onAppearanceObjectId] = $relatedObjectIds;

        return [
            IndirectObject::stream(
                $offAppearanceObjectId,
                $this->appearanceStreamDictionaryContents(),
                $this->appearanceStreamContents(false),
            ),
            IndirectObject::stream(
                $onAppearanceObjectId,
                $this->appearanceStreamDictionaryContents(),
                $this->appearanceStreamContents(true),
            ),
        ];
    }

    private function appearanceStreamDictionaryContents(): string
    {
        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($this->width)
            . ' '
            . $this->formatNumber($this->height)
            . '] /Resources << >> /Length 0 >>';
    }

    private function appearanceStreamContents(bool $checked): string
    {
        $lines = [
            '1 g',
            '0 G',
            '1 w',
            '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
            'B',
        ];

        if ($checked) {
            $lines[] = $this->formatNumber($this->width * 0.2) . ' ' . $this->formatNumber($this->height * 0.55) . ' m';
            $lines[] = $this->formatNumber($this->width * 0.42) . ' ' . $this->formatNumber($this->height * 0.25) . ' l';
            $lines[] = $this->formatNumber($this->width * 0.42) . ' ' . $this->formatNumber($this->height * 0.25) . ' m';
            $lines[] = $this->formatNumber($this->width * 0.8) . ' ' . $this->formatNumber($this->height * 0.8) . ' l';
            $lines[] = 'S';
        }

        return implode("\n", $lines);
    }
}
