<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;

use Kalle\Pdf\Writer\IndirectObject;

use function strlen;

final readonly class UnderlineAnnotation implements AppearanceStreamAnnotation, PageAnnotation, RelatedObjectsPageAnnotation, SupportsPopupAnnotation, PdfUaTaggedPageAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?Color $color = null,
        public ?string $contents = null,
        public ?string $title = null,
        public ?int $structParentId = null,
        public ?PopupAnnotationDefinition $popup = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Underline annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Underline annotation height must be greater than zero.');
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
            popup: $this->popup,
        );
    }

    public function withPopup(PopupAnnotationDefinition $popup): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            color: $this->color,
            contents: $this->contents,
            title: $this->title,
            structParentId: $this->structParentId,
            popup: $popup,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /Underline',
            '/Rect ' . $this->rect($this->x, $this->y, $this->width, $this->height),
            '/P ' . $context->pageObjectId . ' 0 R',
            '/QuadPoints ' . $this->quadPoints($this->x, $this->y, $this->width, $this->height),
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

        if ($this->popup !== null) {
            $entries[] = '/Popup ' . $context->relatedObjectId(0) . ' 0 R';
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
        $y = max($this->height * 0.1, 0.5);

        return implode("\n", [
            $this->strokingColorOperator($this->color ?? Color::black()),
            '1 w',
            '0 ' . $this->formatNumber($y) . ' m',
            $this->formatNumber($this->width) . ' ' . $this->formatNumber($y) . ' l',
            'S',
        ]);
    }

    public function relatedObjectCount(): int
    {
        return $this->popup !== null ? 1 : 0;
    }

    public function relatedObjects(PageAnnotationRenderContext $context): array
    {
        if ($this->popup === null) {
            return [];
        }

        return [
            IndirectObject::plain(
                $context->relatedObjectId(0),
                (new PopupAnnotation($this->popup))->pdfObjectContents($context),
            ),
        ];
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
