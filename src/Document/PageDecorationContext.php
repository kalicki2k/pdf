<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

final class PageDecorationContext
{
    private DefaultDocumentBuilder $builder;

    public function __construct(
        DefaultDocumentBuilder $builder,
        private readonly Page $page,
        private readonly int $pageNumber,
        private readonly int $totalPages,
    ) {
        $this->builder = $builder;
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

    public function text(string $text, ?TextOptions $options = null): self
    {
        $this->builder = $this->builder->text($text, $options);

        return $this;
    }

    /**
     * @param list<TextSegment> $segments
     */
    public function textSegments(array $segments, ?TextOptions $options = null): self
    {
        $this->builder = $this->builder->textSegments($segments, $options);

        return $this;
    }

    public function paragraph(string $text, ?TextOptions $options = null): self
    {
        $this->builder = $this->builder->paragraph($text, $options);

        return $this;
    }

    public function heading(string $text, int $level = 1, ?TextOptions $options = null): self
    {
        $this->builder = $this->builder->heading($text, $level, $options);

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

    public function decoratedPage(): Page
    {
        return $this->builder->buildPageDecorationResult();
    }
}
