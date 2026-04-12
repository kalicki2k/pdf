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

final readonly class HighlightAnnotation implements AppearanceStreamAnnotation, PageAnnotation
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?Color $color = null,
        public ?string $contents = null,
        public ?string $title = null,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Highlight annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Highlight annotation height must be greater than zero.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            color: $this->color,
            contents: $this->contents,
            title: $this->title,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /Highlight',
            '/Rect [' . $this->formatNumber($this->x) . ' '
            . $this->formatNumber($this->y) . ' '
            . $this->formatNumber($this->x + $this->width) . ' '
            . $this->formatNumber($this->y + $this->height) . ']',
            '/P ' . $context->pageObjectId . ' 0 R',
            '/QuadPoints ['
            . $this->formatNumber($this->x) . ' '
            . $this->formatNumber($this->y + $this->height) . ' '
            . $this->formatNumber($this->x + $this->width) . ' '
            . $this->formatNumber($this->y + $this->height) . ' '
            . $this->formatNumber($this->x) . ' '
            . $this->formatNumber($this->y) . ' '
            . $this->formatNumber($this->x + $this->width) . ' '
            . $this->formatNumber($this->y)
            . ']',
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($context->printable) {
            $entries[] = '/F 4';
        }

        if ($this->color !== null) {
            $entries[] = '/C [' . implode(' ', array_map($this->formatNumber(...), $this->color->components())) . ']';
        }

        if ($this->contents !== null && $this->contents !== '') {
            $entries[] = '/Contents ' . $this->pdfString($this->contents);
        }

        if ($this->title !== null && $this->title !== '') {
            $entries[] = '/T ' . $this->pdfString($this->title);
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
        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($this->width) . ' '
            . $this->formatNumber($this->height)
            . '] /Resources << >> /Length '
            . strlen($this->appearanceStreamContents())
            . ' >>';
    }

    public function appearanceStreamContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        $color = $this->color ?? Color::rgb(1, 1, 0);

        return $this->nonStrokingColorOperator($color) . "\n0 0 "
            . $this->formatNumber($this->width) . ' '
            . $this->formatNumber($this->height)
            . " re\nf";
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
}
