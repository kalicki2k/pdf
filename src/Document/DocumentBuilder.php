<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Debug\DebugSink;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Drawing\Path;
use Kalle\Pdf\Drawing\StrokeStyle;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\CaretAnnotationOptions;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\InkAnnotationOptions;
use Kalle\Pdf\Page\LineAnnotationOptions;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\MarkupAnnotationOptions;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\PolygonAnnotationOptions;
use Kalle\Pdf\Page\PolyLineAnnotationOptions;
use Kalle\Pdf\Page\ShapeAnnotationOptions;
use Kalle\Pdf\Page\StampAnnotationOptions;
use Kalle\Pdf\Page\TextAnnotationOptions;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use Psr\Log\LoggerInterface;

interface DocumentBuilder
{
    public function debug(DebugConfig $config): self;

    public function withDebugSink(DebugSink $sink): self;

    public function withLogger(LoggerInterface $logger): self;

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

    /**
     * @param callable(PageDecorationContext, int): void $renderer
     */
    public function header(callable $renderer): self;

    /**
     * @param callable(PageDecorationContext, int): bool $predicate
     * @param callable(PageDecorationContext, int): void $renderer
     */
    public function headerOn(callable $predicate, callable $renderer): self;

    /**
     * @param callable(PageDecorationContext, int): void $renderer
     */
    public function footer(callable $renderer): self;

    /**
     * @param callable(PageDecorationContext, int): bool $predicate
     * @param callable(PageDecorationContext, int): void $renderer
     */
    public function footerOn(callable $predicate, callable $renderer): self;

    public function pageNumbers(
        TextOptions $options,
        string $template = 'Page {{page}} / {{pages}}',
        bool $footer = true,
    ): self;

    public function content(string $content): self;

    public function text(string $text, ?TextOptions $options = null): self;

    /**
     * @param list<TextSegment> $segments
     */
    public function textSegments(array $segments, ?TextOptions $options = null): self;

    public function paragraph(string $text, ?TextOptions $options = null): self;

    public function heading(string $text, int $level = 1, ?TextOptions $options = null): self;

    /**
     * @param list<string> $items
     */
    public function list(array $items, ?ListOptions $list = null, ?TextOptions $text = null): self;

    public function table(Table $table): self;

    public function image(ImageSource $source, ImagePlacement $placement, ?ImageAccessibility $accessibility = null): self;

    public function imageFile(string $path, ImagePlacement $placement, ?ImageAccessibility $accessibility = null): self;

    public function line(float $x1, float $y1, float $x2, float $y2, ?StrokeStyle $stroke = null): self;

    public function rectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        ?StrokeStyle $stroke = null,
        ?Color $fillColor = null,
    ): self;

    public function roundedRectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius,
        ?StrokeStyle $stroke = null,
        ?Color $fillColor = null,
    ): self;

    public function path(Path $path, ?StrokeStyle $stroke = null, ?Color $fillColor = null): self;

    public function attachment(
        string $filename,
        string $contents,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $associatedFileRelationship = null,
    ): self;

    public function attachmentFromFile(
        string $path,
        ?string $filename = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $associatedFileRelationship = null,
    ): self;

    public function textField(
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $value = null,
        ?string $alternativeName = null,
        ?string $defaultValue = null,
        float $fontSize = 12.0,
        bool $multiline = false,
    ): self;

    public function checkbox(
        string $name,
        float $x,
        float $y,
        float $size,
        bool $checked = false,
        ?string $alternativeName = null,
    ): self;

    public function radioButton(
        string $groupName,
        string $exportValue,
        float $x,
        float $y,
        float $size,
        bool $checked = false,
        ?string $alternativeName = null,
        ?string $groupAlternativeName = null,
    ): self;

    /**
     * @param array<string, string> $options
     */
    public function comboBox(
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        array $options,
        ?string $value = null,
        ?string $alternativeName = null,
        ?string $defaultValue = null,
        float $fontSize = 12.0,
    ): self;

    /**
     * @param array<string, string> $options
     * @param list<string>|string|null $value
     * @param list<string>|string|null $defaultValue
     */
    public function listBox(
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        array $options,
        string | array | null $value = null,
        ?string $alternativeName = null,
        string | array | null $defaultValue = null,
        float $fontSize = 12.0,
    ): self;

    public function pushButton(
        string $name,
        string $label,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $alternativeName = null,
        ?string $url = null,
        float $fontSize = 12.0,
    ): self;

    public function signatureField(
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $alternativeName = null,
    ): self;

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

    public function underlineAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    public function underlineAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        MarkupAnnotationOptions $options,
    ): self;

    public function strikeOutAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    public function strikeOutAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        MarkupAnnotationOptions $options,
    ): self;

    public function squigglyAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    public function squigglyAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        MarkupAnnotationOptions $options,
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

    public function stampAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        string $icon = 'Draft',
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    public function stampAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        StampAnnotationOptions $options,
    ): self;

    public function squareAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    public function squareAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        ShapeAnnotationOptions $options,
    ): self;

    public function circleAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    public function circleAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        ShapeAnnotationOptions $options,
    ): self;

    public function caretAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $contents = null,
        ?string $title = null,
        string $symbol = 'None',
    ): self;

    public function caretAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        CaretAnnotationOptions $options,
    ): self;

    /**
     * @param list<list<array{0: float, 1: float}>> $paths
     */
    public function inkAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        array $paths,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    /**
     * @param list<list<array{0: float, 1: float}>> $paths
     */
    public function inkAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        array $paths,
        InkAnnotationOptions $options,
    ): self;

    public function lineAnnotation(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    public function lineAnnotationWithOptions(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        LineAnnotationOptions $options,
    ): self;

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function polyLineAnnotation(
        array $vertices,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function polyLineAnnotationWithOptions(
        array $vertices,
        PolyLineAnnotationOptions $options,
    ): self;

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function polygonAnnotation(
        array $vertices,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
    ): self;

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function polygonAnnotationWithOptions(
        array $vertices,
        PolygonAnnotationOptions $options,
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

    public function addOutline(Outline $outline): self;

    public function outline(string $title): self;

    public function outlineAt(string $title, int $pageNumber, ?float $x = null, ?float $y = null): self;

    public function outlineLevel(string $title, int $level): self;

    public function outlineAtLevel(string $title, int $level, int $pageNumber, ?float $x = null, ?float $y = null): self;

    public function outlineClosed(string $title): self;

    public function outlineAtClosed(string $title, int $pageNumber, ?float $x = null, ?float $y = null): self;

    public function outlineLevelClosed(string $title, int $level): self;

    public function outlineAtLevelClosed(string $title, int $level, int $pageNumber, ?float $x = null, ?float $y = null): self;

    public function outlineChild(string $title): self;

    public function outlineChildClosed(string $title): self;

    public function outlineSibling(string $title): self;

    public function outlineSiblingClosed(string $title): self;

    public function tableOfContents(?TableOfContentsOptions $options = null): self;

    public function tableOfContentsEntry(string $title): self;

    public function tableOfContentsEntryAt(string $title, int $pageNumber, ?float $x = null, ?float $y = null): self;

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
