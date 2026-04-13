<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function array_map;
use function array_shift;
use function count;

use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Writer\IndirectObject;

use function max;
use function min;
use function strlen;

final readonly class PolyLineAnnotation implements AppearanceStreamAnnotation, PageAnnotation, RelatedObjectsPageAnnotation, SupportsPopupAnnotation, PdfUaTaggedPageAnnotation
{
    use FormatsPdfAnnotationValues;

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function __construct(
        public array $vertices,
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
        if (count($this->vertices) < 2) {
            throw new InvalidArgumentException('PolyLine annotation requires at least two vertices.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            vertices: $this->vertices,
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
            vertices: $this->vertices,
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
        $xValues = array_map(static fn (array $vertex): float => $vertex[0], $this->vertices);
        $yValues = array_map(static fn (array $vertex): float => $vertex[1], $this->vertices);
        $vertexValues = [];

        foreach ($this->vertices as [$x, $y]) {
            $vertexValues[] = $this->formatNumber($x);
            $vertexValues[] = $this->formatNumber($y);
        }

        $entries = [
            '/Type /Annot',
            '/Subtype /PolyLine',
            '/Rect [' . $this->formatNumber(min(...$xValues)) . ' '
            . $this->formatNumber(min(...$yValues)) . ' '
            . $this->formatNumber(max(...$xValues)) . ' '
            . $this->formatNumber(max(...$yValues)) . ']',
            '/P ' . $context->pageObjectId . ' 0 R',
            '/Vertices [' . implode(' ', $vertexValues) . ']',
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
        $xValues = array_map(static fn (array $vertex): float => $vertex[0], $this->vertices);
        $yValues = array_map(static fn (array $vertex): float => $vertex[1], $this->vertices);
        $width = max(max(...$xValues) - min(...$xValues), 1.0);
        $height = max(max(...$yValues) - min(...$yValues), 1.0);

        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($width) . ' '
            . $this->formatNumber($height)
            . '] /Resources << >> /Length '
            . strlen($this->appearanceStreamContents())
            . ' >>';
    }

    public function appearanceStreamContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        $xValues = array_map(static fn (array $vertex): float => $vertex[0], $this->vertices);
        $yValues = array_map(static fn (array $vertex): float => $vertex[1], $this->vertices);
        $minX = min(...$xValues);
        $minY = min(...$yValues);
        $localVertices = $this->vertices;
        $first = array_shift($localVertices);
        $commands = [
            $this->strokingColorOperator($this->color ?? Color::black()),
            $this->formatNumber($this->borderStyle !== null ? $this->borderStyle->width : 1.0) . ' w',
        ];

        $commands[] = $this->formatNumber($first[0] - $minX) . ' ' . $this->formatNumber($first[1] - $minY) . ' m';

        foreach ($localVertices as [$x, $y]) {
            $commands[] = $this->formatNumber($x - $minX) . ' ' . $this->formatNumber($y - $minY) . ' l';
        }

        $commands[] = 'S';

        return implode("\n", $commands);
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
