<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Drawing\GraphicsAccessibility;
use Kalle\Pdf\Drawing\Path;
use Kalle\Pdf\Drawing\StrokeStyle;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use LogicException;

final class PageDecorationContext
{
    public function __construct(private DocumentBuilder $builder, private readonly Page $page, private readonly int $pageNumber, private readonly int $totalPages)
    {
    }

    public function page(): Page
    {
        return $this->page;
    }

    public function pageNumber(): int
    {
        return $this->pageNumber;
    }

    public function totalPages(): int
    {
        return $this->totalPages;
    }

    public function isFirstPage(): bool
    {
        return $this->pageNumber === 1;
    }

    public function isLastPage(): bool
    {
        return $this->pageNumber === $this->totalPages;
    }

    public function content(string $content): self
    {
        $this->builder = $this->builder->content($content);

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function text(string | array $text, ?TextOptions $options = null): self
    {
        $this->builder = $this->builder->text($text, $options);

        return $this;
    }

    /**
     * @param list<string|TextSegment> $lines
     */
    public function textLines(array $lines, ?TextOptions $options = null): self
    {
        $this->builder = $this->builder->textLines($lines, $options);

        return $this;
    }

    public function beginStructure(TaggedStructureTag $tag): self
    {
        $this->builder = $this->builder->beginStructure($tag);

        return $this;
    }

    public function endStructure(): self
    {
        $this->builder = $this->builder->endStructure();

        return $this;
    }

    public function image(ImageSource $source, ImagePlacement $placement, ?ImageAccessibility $accessibility = null): self
    {
        $this->builder = $this->builder->image($source, $placement, $accessibility);

        return $this;
    }

    public function imageFile(string $path, ImagePlacement $placement, ?ImageAccessibility $accessibility = null): self
    {
        $this->builder = $this->builder->imageFile($path, $placement, $accessibility);

        return $this;
    }

    public function line(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        ?StrokeStyle $stroke = null,
        ?GraphicsAccessibility $accessibility = null,
    ): self {
        $this->builder = $this->builder->line($x1, $y1, $x2, $y2, $stroke, $accessibility);

        return $this;
    }

    public function rectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        ?StrokeStyle $stroke = null,
        ?Color $fillColor = null,
        ?GraphicsAccessibility $accessibility = null,
    ): self {
        $this->builder = $this->builder->rectangle($x, $y, $width, $height, $stroke, $fillColor, $accessibility);

        return $this;
    }

    public function roundedRectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius,
        ?StrokeStyle $stroke = null,
        ?Color $fillColor = null,
        ?GraphicsAccessibility $accessibility = null,
    ): self {
        $this->builder = $this->builder->roundedRectangle($x, $y, $width, $height, $radius, $stroke, $fillColor, $accessibility);

        return $this;
    }

    public function path(
        Path $path,
        ?StrokeStyle $stroke = null,
        ?Color $fillColor = null,
        ?GraphicsAccessibility $accessibility = null,
    ): self {
        $this->builder = $this->builder->path($path, $stroke, $fillColor, $accessibility);

        return $this;
    }

    public function decoratedPage(): Page
    {
        if (!$this->builder instanceof DefaultDocumentBuilder) {
            throw new LogicException('Page decoration requires the default document builder implementation.');
        }

        return $this->builder->buildPageDecorationResult();
    }
}
