<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;

use function implode;
use function strlen;

final readonly class StampAnnotation implements AppearanceStreamAnnotation, PageAnnotation, PdfUaTaggedPageAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $icon = 'Draft',
        public ?Color $color = null,
        public ?string $contents = null,
        public ?string $title = null,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Stamp annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Stamp annotation height must be greater than zero.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            icon: $this->icon,
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
            '/Subtype /Stamp',
            '/Rect ' . $this->rect($this->x, $this->y, $this->width, $this->height),
            '/P ' . $context->pageObjectId . ' 0 R',
            '/Name /' . $this->pdfName($this->icon),
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($context->printable) {
            $entries[] = '/F 4';
        }

        if ($this->color !== null) {
            $entries[] = '/C ' . $this->pdfColorArray($this->color);
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
        $color = $this->color ?? Color::rgb(0.85, 0.2, 0.2);

        return implode("\n", [
            $this->strokingColorOperator($color),
            '1 w',
            '0.5 0.5 ' . $this->formatNumber($this->width - 1.0) . ' ' . $this->formatNumber($this->height - 1.0) . ' re',
            'S',
        ]);
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
