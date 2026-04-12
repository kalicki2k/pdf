<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;

use Kalle\Pdf\Color\ColorSpace;

use function number_format;
use function rtrim;
use function str_replace;
use function strlen;

final readonly class FreeTextAnnotation implements AppearanceStreamAnnotation, PageAnnotation, TaggedPageAnnotation
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $contents,
        public string $fontAlias,
        public float $fontSize,
        public string $appearanceContents,
        public ?Color $textColor = null,
        public ?Color $borderColor = null,
        public ?Color $fillColor = null,
        public ?string $title = null,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('FreeText annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('FreeText annotation height must be greater than zero.');
        }

        if ($this->contents === '') {
            throw new InvalidArgumentException('FreeText annotation contents must not be empty.');
        }

        if ($this->fontSize <= 0.0) {
            throw new InvalidArgumentException('FreeText annotation font size must be greater than zero.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            contents: $this->contents,
            fontAlias: $this->fontAlias,
            fontSize: $this->fontSize,
            appearanceContents: $this->appearanceContents,
            textColor: $this->textColor,
            borderColor: $this->borderColor,
            fillColor: $this->fillColor,
            title: $this->title,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /FreeText',
            '/Rect [' . $this->formatNumber($this->x) . ' '
            . $this->formatNumber($this->y) . ' '
            . $this->formatNumber($this->x + $this->width) . ' '
            . $this->formatNumber($this->y + $this->height) . ']',
            '/P ' . $context->pageObjectId . ' 0 R',
            '/Contents ' . $this->pdfString($this->contents),
            '/DA ' . $this->pdfString('/' . $this->fontAlias . ' ' . $this->formatNumber($this->fontSize) . ' Tf ' . $this->nonStrokingColorOperator($this->textColor ?? Color::black())),
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($context->printable) {
            $entries[] = '/F 4';
        }

        if ($this->title !== null && $this->title !== '') {
            $entries[] = '/T ' . $this->pdfString($this->title);
        }

        if ($this->borderColor !== null) {
            $entries[] = '/C [' . implode(' ', array_map($this->formatNumber(...), $this->borderColor->components())) . ']';
        }

        if ($this->fillColor !== null) {
            $entries[] = '/IC [' . implode(' ', array_map($this->formatNumber(...), $this->fillColor->components())) . ']';
        }

        if ($context->appearanceObjectId !== null) {
            $entries[] = '/AP << /N ' . $context->appearanceObjectId . ' 0 R >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function markedContentId(): ?int
    {
        return null;
    }

    public function appearanceStreamDictionaryContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        if ($context === null) {
            throw new InvalidArgumentException('FreeText annotation appearance streams require a render context.');
        }

        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($this->width) . ' '
            . $this->formatNumber($this->height)
            . '] /Resources << /Font << /' . $this->fontAlias . ' ' . $context->fontObjectId($this->fontAlias) . ' 0 R >> >> /Length '
            . strlen($this->appearanceContents)
            . ' >>';
    }

    public function appearanceStreamContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        return $this->appearanceContents;
    }

    private function nonStrokingColorOperator(Color $color): string
    {
        $components = implode(' ', array_map($this->formatNumber(...), $color->components()));

        return match ($color->space) {
            ColorSpace::GRAY => $components . ' g',
            ColorSpace::RGB => $components . ' rg',
            ColorSpace::CMYK => $components . ' k',
        };
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }

    public function taggedAnnotationAltText(): ?string
    {
        return $this->contents;
    }

    public function taggedAnnotationStructureTag(): string
    {
        return 'Annot';
    }
}
