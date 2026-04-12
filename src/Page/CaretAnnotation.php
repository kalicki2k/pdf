<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

use function implode;
use function in_array;
use function strlen;

final readonly class CaretAnnotation implements AppearanceStreamAnnotation, PageAnnotation, TaggedPageAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?string $contents = null,
        public ?string $title = null,
        public string $symbol = 'None',
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Caret annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Caret annotation height must be greater than zero.');
        }

        if (!in_array($this->symbol, ['None', 'P'], true)) {
            throw new InvalidArgumentException('Caret annotation symbol must be "None" or "P".');
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
            title: $this->title,
            symbol: $this->symbol,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /Caret',
            '/Rect ' . $this->rect($this->x, $this->y, $this->width, $this->height),
            '/P ' . $context->pageObjectId . ' 0 R',
            '/Sy /' . $this->pdfName($this->symbol),
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($context->printable) {
            $entries[] = '/F 4';
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
        return implode("\n", [
            '0 G',
            '1 w',
            '0 ' . $this->formatNumber($this->height / 2.0) . ' m',
            $this->formatNumber($this->width / 2.0) . ' ' . $this->formatNumber($this->height) . ' l',
            $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height / 2.0) . ' l',
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
