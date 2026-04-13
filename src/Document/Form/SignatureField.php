<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function count;
use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Writer\IndirectObject;

use function max;

use Override;

/**
 * Visible signature widget without a cryptographic signature value dictionary.
 */
final readonly class SignatureField extends WidgetFormField
{
    #[Override]
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
            throw new InvalidArgumentException('Signature fields require one appearance object ID.');
        }

        return '<< ' . implode(' ', [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Sig',
            '/Border [0 0 1]',
            '/AP << /N ' . $relatedObjectIds[0] . ' 0 R >>',
        ]) . ' >>';
    }

    #[Override]
    public function relatedObjects(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): array {
        if (count($relatedObjectIds) !== 1) {
            throw new InvalidArgumentException('Signature fields require one appearance object ID.');
        }

        return [
            IndirectObject::stream(
                $relatedObjectIds[0],
                $this->appearanceStreamDictionaryContents(),
                $this->appearanceStreamContents(),
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

    private function appearanceStreamContents(): string
    {
        return implode("\n", [
            'q',
            '1 g',
            '0 G',
            '1 w',
            '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
            'B',
            $this->formatNumber(4.0) . ' ' . $this->formatNumber(max(4.0, $this->height * 0.28)) . ' m',
            $this->formatNumber(max(4.0, $this->width - 4.0)) . ' ' . $this->formatNumber(max(4.0, $this->height * 0.28)) . ' l',
            'S',
            'Q',
        ]);
    }
}
