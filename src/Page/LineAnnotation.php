<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;

use Kalle\Pdf\Writer\IndirectObject;

use function max;
use function min;
use function strlen;

final readonly class LineAnnotation implements AppearanceStreamAnnotation, PageAnnotation, RelatedObjectsPageAnnotation, SupportsPopupAnnotation, PdfUaTaggedPageAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public float $x1,
        public float $y1,
        public float $x2,
        public float $y2,
        public ?Color $color = null,
        public ?string $contents = null,
        public ?string $title = null,
        public ?LineEndingStyle $startStyle = null,
        public ?LineEndingStyle $endStyle = null,
        public ?string $subject = null,
        public ?AnnotationBorderStyle $borderStyle = null,
        public ?int $structParentId = null,
        public ?PopupAnnotationDefinition $popup = null,
    ) {
        if ($this->x1 === $this->x2 && $this->y1 === $this->y2) {
            throw new InvalidArgumentException('Line annotation start and end points must differ.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x1: $this->x1,
            y1: $this->y1,
            x2: $this->x2,
            y2: $this->y2,
            color: $this->color,
            contents: $this->contents,
            title: $this->title,
            startStyle: $this->startStyle,
            endStyle: $this->endStyle,
            subject: $this->subject,
            borderStyle: $this->borderStyle,
            structParentId: $structParentId,
            popup: $this->popup,
        );
    }

    public function withPopup(PopupAnnotationDefinition $popup): self
    {
        return new self(
            x1: $this->x1,
            y1: $this->y1,
            x2: $this->x2,
            y2: $this->y2,
            color: $this->color,
            contents: $this->contents,
            title: $this->title,
            startStyle: $this->startStyle,
            endStyle: $this->endStyle,
            subject: $this->subject,
            borderStyle: $this->borderStyle,
            structParentId: $this->structParentId,
            popup: $popup,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /Line',
            '/Rect [' . $this->formatNumber(min($this->x1, $this->x2)) . ' '
            . $this->formatNumber(min($this->y1, $this->y2)) . ' '
            . $this->formatNumber(max($this->x1, $this->x2)) . ' '
            . $this->formatNumber(max($this->y1, $this->y2)) . ']',
            '/P ' . $context->pageObjectId . ' 0 R',
            '/L [' . $this->formatNumber($this->x1) . ' '
            . $this->formatNumber($this->y1) . ' '
            . $this->formatNumber($this->x2) . ' '
            . $this->formatNumber($this->y2) . ']',
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

        if ($this->subject !== null && $this->subject !== '') {
            $entries[] = '/Subj ' . $this->pdfString($this->subject);
        }

        if ($this->borderStyle !== null) {
            $entries[] = '/BS ' . $this->borderStyleDictionary($this->borderStyle);
        }

        if ($this->startStyle !== null || $this->endStyle !== null) {
            $entries[] = '/LE [/' . ($this->startStyle ?? LineEndingStyle::NONE)->value . ' /' . ($this->endStyle ?? LineEndingStyle::NONE)->value . ']';
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
        $width = max(max($this->x1, $this->x2) - min($this->x1, $this->x2), 1.0);
        $height = max(max($this->y1, $this->y2) - min($this->y1, $this->y2), 1.0);

        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($width) . ' '
            . $this->formatNumber($height)
            . '] /Resources << >> /Length '
            . strlen($this->appearanceStreamContents())
            . ' >>';
    }

    public function appearanceStreamContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        $minX = min($this->x1, $this->x2);
        $minY = min($this->y1, $this->y2);
        $borderWidth = $this->borderStyle !== null ? $this->borderStyle->width : 1.0;

        return implode("\n", [
            $this->strokingColorOperator($this->color ?? Color::black()),
            $this->formatNumber($borderWidth) . ' w',
            $this->formatNumber($this->x1 - $minX) . ' ' . $this->formatNumber($this->y1 - $minY) . ' m',
            $this->formatNumber($this->x2 - $minX) . ' ' . $this->formatNumber($this->y2 - $minY) . ' l',
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
                new PopupAnnotation($this->popup)->pdfObjectContents($context),
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
