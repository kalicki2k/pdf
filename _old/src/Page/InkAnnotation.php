<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function array_shift;
use function count;
use function implode;
use function strlen;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;

final readonly class InkAnnotation implements AppearanceStreamAnnotation, PageAnnotation, PdfUaTaggedPageAnnotation
{
    use FormatsPdfAnnotationValues;

    /**
     * @param list<list<array{0: float, 1: float}>> $paths
     */
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public array $paths,
        public ?Color $color = null,
        public ?string $contents = null,
        public ?string $title = null,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Ink annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Ink annotation height must be greater than zero.');
        }

        if ($this->paths === []) {
            throw new InvalidArgumentException('Ink annotation requires at least one path.');
        }

        foreach ($this->paths as $path) {
            if (count($path) === 0) {
                throw new InvalidArgumentException('Ink annotation paths must not be empty.');
            }
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            paths: $this->paths,
            color: $this->color,
            contents: $this->contents,
            title: $this->title,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $inkListEntries = [];

        foreach ($this->paths as $path) {
            $points = [];

            foreach ($path as [$x, $y]) {
                $points[] = $this->formatNumber($x);
                $points[] = $this->formatNumber($y);
            }

            $inkListEntries[] = '[' . implode(' ', $points) . ']';
        }

        $entries = [
            '/Type /Annot',
            '/Subtype /Ink',
            '/Rect ' . $this->rect($this->x, $this->y, $this->width, $this->height),
            '/P ' . $context->pageObjectId . ' 0 R',
            '/InkList [' . implode(' ', $inkListEntries) . ']',
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
        $commands = [
            $this->strokingColorOperator($this->color ?? Color::black()),
            '1 w',
        ];

        foreach ($this->paths as $path) {
            $localPath = $path;
            $firstPoint = array_shift($localPath);

            if ($firstPoint === null) {
                continue;
            }

            $commands[] = $this->formatNumber($firstPoint[0] - $this->x) . ' ' . $this->formatNumber($firstPoint[1] - $this->y) . ' m';

            foreach ($localPath as [$x, $y]) {
                $commands[] = $this->formatNumber($x - $this->x) . ' ' . $this->formatNumber($y - $this->y) . ' l';
            }
        }

        $commands[] = 'S';

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
