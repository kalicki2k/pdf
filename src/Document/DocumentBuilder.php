<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;

interface DocumentBuilder
{
    public function title(string $title): self;

    public function author(string $author): self;

    public function subject(string $subject): self;

    public function language(string $language): self;

    public function creator(string $creator): self;

    public function creatorTool(string $creatorTool): self;

    public function profile(Profile $profile): self;

    public function pageSize(PageSize $size): self;

    public function margin(Margin $margin): self;

    public function content(string $content): self;

    public function text(string $text, ?TextOptions $options = null): self;

    public function paragraph(string $text, ?TextOptions $options = null): self;

    public function glyphs(StandardFontGlyphRun $glyphRun, ?TextOptions $options = null): self;

    public function newPage(?PageOptions $options = null): self;

    public function build(): Document;

    public function contents(): string;

    /**
     * @param resource $stream
     */
    public function writeToStream($stream): void;

    public function writeToFile(string $path): void;
}
