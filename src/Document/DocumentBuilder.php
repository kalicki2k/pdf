<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\TextAnnotationOptions;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

interface DocumentBuilder
{
    public function title(string $title): self;

    public function author(string $author): self;

    public function subject(string $subject): self;

    public function language(string $language): self;

    public function creator(string $creator): self;

    public function creatorTool(string $creatorTool): self;

    public function pdfaOutputIntent(PdfAOutputIntent $outputIntent): self;

    public function encryption(Encryption $encryption): self;

    public function profile(Profile $profile): self;

    public function pageSize(PageSize $size): self;

    public function margin(Margin $margin): self;

    public function content(string $content): self;

    public function text(string $text, ?TextOptions $options = null): self;

    /**
     * @param list<TextSegment> $segments
     */
    public function textSegments(array $segments, ?TextOptions $options = null): self;

    public function paragraph(string $text, ?TextOptions $options = null): self;

    public function table(Table $table): self;

    public function image(ImageSource $source, ImagePlacement $placement, ?ImageAccessibility $accessibility = null): self;

    public function imageFile(string $path, ImagePlacement $placement, ?ImageAccessibility $accessibility = null): self;

    public function textAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        string $contents,
        ?string $title = null,
        string $icon = 'Note',
        bool $open = false,
    ): self;

    public function textAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        string $contents,
        TextAnnotationOptions $options,
    ): self;

    public function highlightAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    public function highlightAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        HighlightAnnotationOptions $options,
    ): self;

    public function freeTextAnnotation(
        string $contents,
        float $x,
        float $y,
        float $width,
        float $height,
        ?TextOptions $options = null,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $title = null,
    ): self;

    public function freeTextAnnotationWithOptions(
        string $contents,
        float $x,
        float $y,
        float $width,
        float $height,
        ?TextOptions $textOptions = null,
        ?FreeTextAnnotationOptions $options = null,
    ): self;

    public function link(string $url, float $x, float $y, float $width, float $height, ?string $contents = null, ?string $accessibleLabel = null): self;

    public function linkWithOptions(string $url, float $x, float $y, float $width, float $height, LinkAnnotationOptions $options): self;

    public function linkToNamedDestination(string $name, float $x, float $y, float $width, float $height, ?string $contents = null, ?string $accessibleLabel = null): self;

    public function linkToNamedDestinationWithOptions(string $name, float $x, float $y, float $width, float $height, LinkAnnotationOptions $options): self;

    public function linkToPage(int $pageNumber, float $x, float $y, float $width, float $height, ?string $contents = null, ?string $accessibleLabel = null): self;

    public function linkToPageWithOptions(int $pageNumber, float $x, float $y, float $width, float $height, LinkAnnotationOptions $options): self;

    public function linkToPagePosition(
        int $pageNumber,
        float $targetX,
        float $targetY,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $contents = null,
        ?string $accessibleLabel = null,
    ): self;

    public function linkToPagePositionWithOptions(
        int $pageNumber,
        float $targetX,
        float $targetY,
        float $x,
        float $y,
        float $width,
        float $height,
        LinkAnnotationOptions $options,
    ): self;

    public function namedDestination(string $name): self;

    public function namedDestinationPosition(string $name, float $x, float $y): self;

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
