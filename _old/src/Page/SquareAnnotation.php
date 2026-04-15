<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;
use function strlen;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;

final readonly class SquareAnnotation implements AppearanceStreamAnnotation, PageAnnotation, PdfUaTaggedPageAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?Color $borderColor = null,
        public ?Color $fillColor = null,
        public ?string $contents = null,
        public ?string $title = null,
        public ?AnnotationBorderStyle $borderStyle = null,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Square annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Square annotation height must be greater than zero.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            borderColor: $this->borderColor,
            fillColor: $this->fillColor,
            contents: $this->contents,
            title: $this->title,
            borderStyle: $this->borderStyle,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /Square',
            '/Rect ' . $this->rect($this->x, $this->y, $this->width, $this->height),
            '/P ' . $context->pageObjectId . ' 0 R',
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($context->printable) {
            $entries[] = '/F 4';
        }

        if ($this->borderColor !== null) {
            $entries[] = '/C ' . $this->pdfColorArray($this->borderColor);
        }

        if ($this->fillColor !== null) {
            $entries[] = '/IC ' . $this->pdfColorArray($this->fillColor);
        }

        if ($this->contents !== null && $this->contents !== '') {
            $entries[] = '/Contents ' . $this->pdfString($this->contents);
        }

        if ($this->title !== null && $this->title !== '') {
            $entries[] = '/T ' . $this->pdfString($this->title);
        }

        if ($this->borderStyle !== null) {
            $entries[] = '/BS ' . $this->borderStyleDictionary($this->borderStyle);
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
        $commands = [];

        if ($this->fillColor !== null) {
            $commands[] = $this->nonStrokingColorOperator($this->fillColor);
        }

        if ($this->borderColor !== null) {
            $commands[] = $this->strokingColorOperator($this->borderColor);
        }

        $borderWidth = $this->borderStyle !== null ? $this->borderStyle->width : 1.0;
        $inset = max($borderWidth / 2.0, 0.0);
        $commands[] = $this->formatNumber($borderWidth) . ' w';
        $commands[] = $this->formatNumber($inset) . ' ' . $this->formatNumber($inset) . ' '
            . $this->formatNumber(max($this->width - ($inset * 2.0), 0.0)) . ' '
            . $this->formatNumber(max($this->height - ($inset * 2.0), 0.0)) . ' re';
        $commands[] = $this->fillColor !== null && $this->borderColor !== null ? 'B' : ($this->fillColor !== null ? 'f' : 'S');

        return implode("\n", $commands);
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
