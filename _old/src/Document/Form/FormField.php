<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use InvalidArgumentException;
use Kalle\Pdf\Writer\IndirectObject;

abstract readonly class FormField
{
    protected const PDF_A_FORM_FONT_ALIAS = 'F0';

    public function __construct(
        public string $name,
        public ?string $alternativeName = null,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Form field name must not be empty.');
        }

        if ($this->alternativeName === '') {
            throw new InvalidArgumentException('Form field alternative name must not be empty.');
        }
    }

    public function pageNumber(): ?int
    {
        return null;
    }

    /**
     * @param list<int> $relatedObjectIds
     */
    abstract public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string;

    public function relatedObjectCount(): int
    {
        return 0;
    }

    /**
     * @param list<int> $relatedObjectIds
     * @return list<IndirectObject>
     */
    public function relatedObjects(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): array {
        return [];
    }

    public function needsDefaultTextResources(): bool
    {
        return false;
    }

    /**
     * @param list<int> $relatedObjectIds
     * @return array<int, list<int>>
     */
    public function pageAnnotationObjectIds(int $fieldObjectId, array $relatedObjectIds = []): array
    {
        return [];
    }

    protected function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }

    protected function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    protected function pdfName(string $value): string
    {
        $encoded = '';

        foreach (str_split($value) as $character) {
            $ord = ord($character[0]);

            if (
                ($ord >= 48 && $ord <= 57)
                || ($ord >= 65 && $ord <= 90)
                || ($ord >= 97 && $ord <= 122)
                || $character === '-'
                || $character === '_'
                || $character === '.'
            ) {
                $encoded .= $character;

                continue;
            }

            $encoded .= '#' . strtoupper(str_pad(dechex($ord), 2, '0', STR_PAD_LEFT));
        }

        return $encoded;
    }

    protected function renderPdfAAppearanceDictionary(FormFieldRenderContext $context, float $width, float $height): string
    {
        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($width)
            . ' '
            . $this->formatNumber($height)
            . '] /Resources << /Font << /'
            . $context->requiresDefaultTextFontAlias()
            . ' '
            . $context->requiresDefaultTextFontObjectId()
            . ' 0 R >> >> /Length 0 >>';
    }

    protected function pdfAEncodedTextHex(FormFieldRenderContext $context, string $text): string
    {
        return strtoupper(bin2hex($context->requiresDefaultTextFont()->encodeUnicodeText($text)));
    }

    protected function pdfAFieldDa(FormFieldRenderContext $context, float $fontSize): string
    {
        return '/' . $context->requiresDefaultTextFontAlias() . ' ' . $this->formatNumber($fontSize) . ' Tf 0 g';
    }
}
