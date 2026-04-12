<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function implode;

use InvalidArgumentException;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableCell;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Font\EmbeddedFontDefinition;

use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Font\StandardFontMetrics;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\TableCellLayout;
use Kalle\Pdf\Layout\Table\TableLayout;
use Kalle\Pdf\Layout\Table\TableLayoutCalculator;
use Kalle\Pdf\Layout\Table\TableRowGroupLayout;
use Kalle\Pdf\Layout\Table\VerticalAlign;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\NamedDestination;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageImage;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\TextAnnotation;
use Kalle\Pdf\Page\TextAnnotationOptions;
use Kalle\Pdf\Text\MappedTextRun;
use Kalle\Pdf\Text\ShapedTextRun;
use Kalle\Pdf\Text\SimpleFontRunMapper;
use Kalle\Pdf\Text\SimpleTextShaper;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

use Kalle\Pdf\Writer\FileOutput;

use Kalle\Pdf\Writer\StreamOutput;

use Kalle\Pdf\Writer\StringOutput;

use function mb_ord;

use Throwable;

class DefaultDocumentBuilder implements DocumentBuilder
{
    /** @var list<Page> */
    private array $pages = [];
    private ?PageSize $defaultPageSize = null;
    private ?Margin $defaultPageMargin = null;
    private ?PageSize $currentPageSize = null;
    private string $currentPageContents = '';
    /** @var array<string, PageFont> */
    private array $currentPageFontResources = [];
    /** @var array<string, ImageSource> */
    private array $currentPageImageResources = [];
    /** @var list<PageImage> */
    private array $currentPageImages = [];
    /** @var list<PageAnnotation> */
    private array $currentPageAnnotations = [];
    /** @var list<NamedDestination> */
    private array $currentPageNamedDestinations = [];
    private int $currentPageNextMarkedContentId = 0;
    /** @var array<int, array{captionReferences: list<array{pageIndex: int, markedContentId: int}>, headerRows: array<int, array{cells: array<int, array{header: bool, headerScope: ?TableHeaderScope, rowspan: int, colspan: int, references: list<array{pageIndex: int, markedContentId: int}>}>}>, bodyRows: array<int, array{cells: array<int, array{header: bool, headerScope: ?TableHeaderScope, rowspan: int, colspan: int, references: list<array{pageIndex: int, markedContentId: int}>}>}>, footerRows: array<int, array{cells: array<int, array{header: bool, headerScope: ?TableHeaderScope, rowspan: int, colspan: int, references: list<array{pageIndex: int, markedContentId: int}>}>}>}> */
    private array $taggedTables = [];
    private int $nextTaggedTableId = 0;
    private ?Margin $currentPageMargin = null;
    private ?float $currentPageCursorY = null;
    private ?Color $currentPageBackgroundColor = null;
    private ?string $currentPageLabel = null;
    private ?string $currentPageName = null;
    private ?string $title = null;
    private ?string $author = null;
    private ?string $subject = null;
    private ?string $language = null;
    private ?string $creator = null;
    private ?string $creatorTool = null;
    private ?PdfAOutputIntent $pdfaOutputIntent = null;
    private ?Encryption $encryption = null;
    private ?Profile $profile = null;

    public static function make(): self
    {
        return new self();
    }

    public function title(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function author(string $author): self
    {
        $clone = clone $this;
        $clone->author = $author;

        return $clone;
    }

    public function subject(string $subject): DocumentBuilder
    {
        $clone = clone $this;
        $clone->subject = $subject;

        return $clone;
    }

    public function language(string $language): DocumentBuilder
    {
        $clone = clone $this;
        $clone->language = $language;

        return $clone;
    }

    public function creator(string $creator): DocumentBuilder
    {
        $clone = clone $this;
        $clone->creator = $creator;

        return $clone;
    }

    public function creatorTool(string $creatorTool): DocumentBuilder
    {
        $clone = clone $this;
        $clone->creatorTool = $creatorTool;

        return $clone;
    }

    public function pdfaOutputIntent(PdfAOutputIntent $outputIntent): DocumentBuilder
    {
        $clone = clone $this;
        $clone->pdfaOutputIntent = $outputIntent;

        return $clone;
    }

    public function encryption(Encryption $encryption): DocumentBuilder
    {
        $clone = clone $this;
        $clone->encryption = $encryption;

        return $clone;
    }

    public function profile(Profile $profile): DocumentBuilder
    {
        $clone = clone $this;
        $clone->profile = $profile;

        return $clone;
    }

    public function pageSize(PageSize $size): DocumentBuilder
    {
        $clone = clone $this;
        $clone->defaultPageSize = $size;
        $clone->currentPageSize = $size;

        return $clone;
    }

    public function margin(Margin $margin): DocumentBuilder
    {
        $clone = clone $this;
        $clone->defaultPageMargin = $margin;
        $clone->currentPageMargin = $margin;
        $clone->currentPageCursorY = null;

        return $clone;
    }

    public function content(string $content): DocumentBuilder
    {
        $clone = clone $this;
        $clone->currentPageContents = $content;

        return $clone;
    }

    public function text(string $text, ?TextOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= new TextOptions();
        $font = $options->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($options->embeddedFont)
            : StandardFontDefinition::from($options->fontName);
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($options, $font);
        $wrappedLines = $textFlow->wrapTextLines($text, $options, $font, $placement['x']);
        $shapedLines = $clone->shapeWrappedTextLines($wrappedLines, $options, $font);
        $renderState = $clone->prepareTextRenderState($text, $options, $font, $shapedLines);

        $textResult = $this->buildWrappedTextContent(
            $wrappedLines,
            $shapedLines,
            $options,
            $textFlow,
            $placement['x'],
            $placement['y'],
            $renderState['fontAlias'],
            $font,
            $renderState['embeddedPageFont'],
            $renderState['useHexString'],
            ($this->profile ?? Profile::standard())->version(),
        );
        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $textResult['contents'],
        );
        $clone->currentPageAnnotations = [...$clone->currentPageAnnotations, ...$textResult['annotations']];
        $clone->currentPageCursorY = $textFlow->nextCursorY($options, $placement['y'], count($wrappedLines));

        return $clone;
    }

    public function paragraph(string $text, ?TextOptions $options = null): DocumentBuilder
    {
        return $this->text($text, $options);
    }

    public function textSegments(array $segments, ?TextOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= new TextOptions();

        if ($segments === []) {
            return $clone;
        }

        $text = implode('', array_map(
            static fn (TextSegment $segment): string => $segment->text,
            $segments,
        ));
        $font = $options->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($options->embeddedFont)
            : StandardFontDefinition::from($options->fontName);
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($options, $font);
        $wrappedSegmentLines = $textFlow->wrapSegmentLines($segments, $options, $font, $placement['x']);
        $renderState = $clone->prepareTextRenderState($text, $options, $font, []);
        $textResult = $clone->buildWrappedTextSegmentsContent(
            $wrappedSegmentLines,
            $options,
            $textFlow,
            $placement['x'],
            $placement['y'],
            $renderState['fontAlias'],
            $font,
            $renderState['embeddedPageFont'],
            $renderState['useHexString'],
            ($this->profile ?? Profile::standard())->version(),
        );

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $textResult['contents'],
        );
        $clone->currentPageAnnotations = [...$clone->currentPageAnnotations, ...$textResult['annotations']];
        $clone->currentPageCursorY = $textFlow->nextCursorY($options, $placement['y'], count($wrappedSegmentLines));

        return $clone;
    }

    public function table(Table $table): DocumentBuilder
    {
        if ($table->rows === []) {
            return clone $this;
        }

        $clone = clone $this;
        $font = $table->textOptions->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($table->textOptions->embeddedFont)
            : StandardFontDefinition::from($table->textOptions->fontName);
        $calculator = $this->tableLayoutCalculator();

        $page = $clone->buildCurrentPage();
        $contentArea = $page->contentArea();
        ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
        $columnWidths = $calculator->resolveColumnWidths($table, $tableWidth);
        $captionLayout = $table->caption === null
            ? null
            : $clone->layoutTableCaption($table, new TextFlow($page), $font, $tableWidth);
        $headerLayout = $table->headerRows === []
            ? null
            : $calculator->layoutRows($table->headerRows, $table, $columnWidths, new TextFlow($page), $font);
        $tableLayout = $calculator->layoutTable($table, $columnWidths, new TextFlow($page), $font);
        $footerLayout = $table->footerRows === []
            ? null
            : $calculator->layoutRows($table->footerRows, $table, $columnWidths, new TextFlow($page), $font);
        $taggedTableId = ($clone->profile ?? Profile::standard())->requiresTaggedPdf()
            ? $clone->registerTaggedTable($headerLayout, $tableLayout, $footerLayout)
            : null;
        $explicitStartY = $table->placement?->y;

        if ($explicitStartY !== null && $clone->currentPageCursorY !== null && $explicitStartY > $clone->currentPageCursorY) {
            throw new InvalidArgumentException(
                'Explicit table placement y must not be above the current flow cursor on the page.',
            );
        }

        $cursorY = $explicitStartY ?? $clone->currentPageCursorY ?? $contentArea->top;
        $headerRenderedOnCurrentPage = false;
        $minimumTableSegmentHeight = $table->cellPadding->vertical() + $clone->lineHeightForTable($table);
        $minimumTableStartHeight = $minimumTableSegmentHeight + ($headerLayout?->totalHeight() ?? 0.0);

        if ($captionLayout !== null) {
            if (($captionLayout['height'] + $minimumTableStartHeight) > $contentArea->height()) {
                throw new InvalidArgumentException('Table caption leaves no space for table content on a fresh page.');
            }

            if (($captionLayout['height'] + $minimumTableStartHeight) > ($cursorY - $contentArea->bottom) && $explicitStartY !== null) {
                throw new InvalidArgumentException('Explicit table placement y leaves no space for caption and table start.');
            }

            if (($captionLayout['height'] + $minimumTableStartHeight) > ($cursorY - $contentArea->bottom) && $clone->currentPageCursorY !== null) {
                $clone->startOverflowPage();
                $page = $clone->buildCurrentPage();
                $contentArea = $page->contentArea();
                ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                $cursorY = $contentArea->top;
            }

            $clone->renderTableCaption($captionLayout, $table->caption, $font, $cursorY, $tableLeftX, $taggedTableId);
            $cursorY -= $captionLayout['height'];
            $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
        }

        if ($headerLayout !== null) {
            if ($headerLayout->totalHeight() > ($cursorY - $contentArea->bottom) && $explicitStartY !== null) {
                throw new InvalidArgumentException('Explicit table placement y leaves no space for the configured header rows.');
            }

            if ($headerLayout->totalHeight() > ($cursorY - $contentArea->bottom) && $clone->currentPageCursorY !== null) {
                $clone->startOverflowPage();
                $page = $clone->buildCurrentPage();
                $contentArea = $page->contentArea();
                ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                $cursorY = $contentArea->top;
            }

            $clone->renderTableLayout($table, $headerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'header');
            $cursorY -= $headerLayout->totalHeight();
            $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
            $headerRenderedOnCurrentPage = true;
        }

        foreach ($tableLayout->rowGroups as $rowGroup) {
            $segmentOffset = 0.0;

            while ($segmentOffset < $rowGroup->height) {
                $availableHeight = $cursorY - $contentArea->bottom;
                $remainingGroupHeight = $rowGroup->height - $segmentOffset;
                $headerHeight = !$headerRenderedOnCurrentPage && $headerLayout !== null && $table->repeatHeaderOnPageBreak
                    ? $headerLayout->totalHeight()
                    : 0.0;
                $availableHeightAfterHeader = $availableHeight - $headerHeight;
                $groupFitsAfterHeader = $remainingGroupHeight <= $availableHeightAfterHeader;
                $groupFitsOnFreshPage = $remainingGroupHeight <= ($contentArea->height() - $headerHeight);

                if (($contentArea->height() - $headerHeight) < $minimumTableSegmentHeight) {
                    throw new InvalidArgumentException('Page content area is too small to render table rows.');
                }

                if (!$groupFitsAfterHeader && $clone->currentPageCursorY !== null && $groupFitsOnFreshPage) {
                    $clone->startOverflowPage();
                    $page = $clone->buildCurrentPage();
                    $contentArea = $page->contentArea();
                    ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                    $cursorY = $contentArea->top;
                    $headerRenderedOnCurrentPage = false;
                    continue;
                }

                if (!$headerRenderedOnCurrentPage && $headerLayout !== null && $table->repeatHeaderOnPageBreak) {
                    if ($availableHeightAfterHeader < $minimumTableSegmentHeight) {
                        throw new InvalidArgumentException('Repeated table headers leave no space for table content on the page.');
                    }

                    $clone->renderTableLayout($table, $headerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'header');
                    $cursorY -= $headerLayout->totalHeight();
                    $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
                    $headerRenderedOnCurrentPage = true;
                    $availableHeight = $cursorY - $contentArea->bottom;
                }

                if ($availableHeight < $minimumTableSegmentHeight) {
                    $clone->startOverflowPage();
                    $page = $clone->buildCurrentPage();
                    $contentArea = $page->contentArea();
                    ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                    $cursorY = $contentArea->top;
                    $headerRenderedOnCurrentPage = false;
                    continue;
                }

                $segmentHeight = min($remainingGroupHeight, $availableHeight);
                $clone->renderTableRowGroupSegment(
                    $table,
                    $tableLayout,
                    $rowGroup,
                    $font,
                    $cursorY,
                    $tableLeftX,
                    $segmentOffset,
                    $segmentHeight,
                    $taggedTableId,
                    'body',
                );
                $segmentOffset += $segmentHeight;
                $cursorY -= $segmentHeight;
                $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);

                if ($segmentOffset < $rowGroup->height) {
                    $clone->startOverflowPage();
                    $page = $clone->buildCurrentPage();
                    $contentArea = $page->contentArea();
                    ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                    $cursorY = $contentArea->top;
                    $headerRenderedOnCurrentPage = false;
                }
            }
        }

        if ($footerLayout !== null) {
            $headerHeight = !$headerRenderedOnCurrentPage && $headerLayout !== null && $table->repeatHeaderOnPageBreak
                ? $headerLayout->totalHeight()
                : 0.0;
            $requiredHeight = $footerLayout->totalHeight() + $headerHeight;

            if ($requiredHeight > $contentArea->height()) {
                throw new InvalidArgumentException('Table footer rows must fit on a fresh page.');
            }

            if ($requiredHeight > ($cursorY - $contentArea->bottom) && $clone->currentPageCursorY !== null) {
                $clone->startOverflowPage();
                $page = $clone->buildCurrentPage();
                $contentArea = $page->contentArea();
                ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                $cursorY = $contentArea->top;
                $headerRenderedOnCurrentPage = false;
            }

            if (!$headerRenderedOnCurrentPage && $headerLayout !== null && $table->repeatHeaderOnPageBreak) {
                $clone->renderTableLayout($table, $headerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'header');
                $cursorY -= $headerLayout->totalHeight();
                $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
                $headerRenderedOnCurrentPage = true;
            }

            $clone->renderTableLayout($table, $footerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'footer');
            $cursorY -= $footerLayout->totalHeight();
            $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
        }

        return $clone;
    }

    public function image(
        ImageSource $source,
        ImagePlacement $placement,
        ?ImageAccessibility $accessibility = null,
    ): DocumentBuilder {
        $clone = clone $this;
        $imageAlias = $clone->imageAliasFor($source);
        [$width, $height] = $this->resolveImageDimensions($source, $placement);
        $markedContentId = $clone->markedContentIdForImage($accessibility);
        $clone->currentPageImages[] = new PageImage($imageAlias, $placement, $accessibility, $markedContentId);

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->buildImageContent($imageAlias, $placement->x, $placement->y, $width, $height, $accessibility, $markedContentId),
        );

        return $clone;
    }

    public function imageFile(
        string $path,
        ImagePlacement $placement,
        ?ImageAccessibility $accessibility = null,
    ): DocumentBuilder {
        return $this->image(ImageSource::fromPath($path), $placement, $accessibility);
    }

    public function textAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        string $contents,
        ?string $title = null,
        string $icon = 'Note',
        bool $open = false,
    ): self {
        return $this->textAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            $contents,
            new TextAnnotationOptions(
                title: $title,
                icon: $icon,
                open: $open,
            ),
        );
    }

    public function textAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        string $contents,
        TextAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new TextAnnotation(
            $x,
            $y,
            $width,
            $height,
            $contents,
            $metadata->title,
            $options->icon,
            $options->open,
        );

        return $clone;
    }

    public function highlightAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->highlightAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            new HighlightAnnotationOptions(
                color: $color,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function highlightAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        HighlightAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new HighlightAnnotation(
            $x,
            $y,
            $width,
            $height,
            $options->color,
            $metadata->contents,
            $metadata->title,
        );

        return $clone;
    }

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
    ): DocumentBuilder {
        return $this->freeTextAnnotationWithOptions(
            $contents,
            $x,
            $y,
            $width,
            $height,
            $options,
            new FreeTextAnnotationOptions(
                textColor: $options?->color,
                borderColor: $borderColor,
                fillColor: $fillColor,
                metadata: new \Kalle\Pdf\Page\AnnotationMetadata(title: $title),
            ),
        );
    }

    public function freeTextAnnotationWithOptions(
        string $contents,
        float $x,
        float $y,
        float $width,
        float $height,
        ?TextOptions $textOptions = null,
        ?FreeTextAnnotationOptions $options = null,
    ): DocumentBuilder {
        $clone = clone $this;
        $textOptions ??= new TextOptions(fontSize: 12.0);
        $options ??= new FreeTextAnnotationOptions();
        $metadata = $options->metadata();
        $resolvedTextColor = $options->textColor ?? $textOptions->color;
        $font = $textOptions->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($textOptions->embeddedFont)
            : StandardFontDefinition::from($textOptions->fontName);
        $appearanceOptions = new TextOptions(
            x: 2.0,
            y: $height - 2.0 - $font->ascent($textOptions->fontSize),
            width: max($width - 4.0, 0.0),
            fontSize: $textOptions->fontSize,
            lineHeight: $textOptions->lineHeight,
            fontName: $textOptions->fontName,
            embeddedFont: $textOptions->embeddedFont,
            fontEncoding: $textOptions->fontEncoding,
            color: $resolvedTextColor,
            kerning: $textOptions->kerning,
            baseDirection: $textOptions->baseDirection,
            align: $textOptions->align,
        );
        $textFlow = new TextFlow(new Page(PageSize::custom($width, $height)));
        $wrappedLines = $textFlow->wrapTextLines($contents, $appearanceOptions, $font, 2.0);
        $shapedLines = $clone->shapeWrappedTextLines($wrappedLines, $appearanceOptions, $font);
        $renderState = $clone->prepareTextRenderState($contents, $appearanceOptions, $font, $shapedLines);
        $appearance = $clone->buildWrappedTextContent(
            $wrappedLines,
            $shapedLines,
            $appearanceOptions,
            $textFlow,
            2.0,
            $height - 2.0 - $font->ascent($textOptions->fontSize),
            $renderState['fontAlias'],
            $font,
            $renderState['embeddedPageFont'],
            $renderState['useHexString'],
            ($this->profile ?? Profile::standard())->version(),
        );
        $appearanceContents = $appearance['contents'];

        if ($options->fillColor !== null || $options->borderColor !== null) {
            $background = [];

            if ($options->fillColor !== null) {
                $background[] = $this->colorFillOperator($options->fillColor);
            }

            if ($options->borderColor !== null) {
                $background[] = $this->colorStrokeOperator($options->borderColor);
                $background[] = '1 w';
                $background[] = '0.5 0.5 ' . $this->formatNumber($width - 1.0) . ' ' . $this->formatNumber($height - 1.0) . ' re';
                $background[] = $options->fillColor !== null ? 'B' : 'S';
            } elseif ($options->fillColor !== null) {
                $background[] = '0 0 ' . $this->formatNumber($width) . ' ' . $this->formatNumber($height) . ' re';
                $background[] = 'f';
            }

            if ($background !== []) {
                $appearanceContents = implode("\n", [...$background, $appearanceContents]);
            }
        }

        $clone->currentPageAnnotations[] = new FreeTextAnnotation(
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            contents: $contents,
            fontAlias: $renderState['fontAlias'],
            fontSize: $textOptions->fontSize,
            appearanceContents: $appearanceContents,
            textColor: $resolvedTextColor,
            borderColor: $options->borderColor,
            fillColor: $options->fillColor,
            title: $metadata->title,
        );

        return $clone;
    }

    public function link(
        string $url,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $contents = null,
        ?string $accessibleLabel = null,
    ): DocumentBuilder {
        return $this->linkWithOptions(
            $url,
            $x,
            $y,
            $width,
            $height,
            new LinkAnnotationOptions(
                contents: $contents,
                accessibleLabel: $accessibleLabel,
            ),
        );
    }

    public function linkWithOptions(
        string $url,
        float $x,
        float $y,
        float $width,
        float $height,
        LinkAnnotationOptions $options,
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->currentPageAnnotations[] = $this->buildRectLinkAnnotation(
            LinkTarget::externalUrl($url),
            $x,
            $y,
            $width,
            $height,
            $options,
        );

        return $clone;
    }

    public function linkToNamedDestination(
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $contents = null,
        ?string $accessibleLabel = null,
    ): DocumentBuilder {
        return $this->linkToNamedDestinationWithOptions(
            $name,
            $x,
            $y,
            $width,
            $height,
            new LinkAnnotationOptions(
                contents: $contents,
                accessibleLabel: $accessibleLabel,
            ),
        );
    }

    public function linkToNamedDestinationWithOptions(
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        LinkAnnotationOptions $options,
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->currentPageAnnotations[] = $this->buildRectLinkAnnotation(
            LinkTarget::namedDestination($name),
            $x,
            $y,
            $width,
            $height,
            $options,
        );

        return $clone;
    }

    public function linkToPage(
        int $pageNumber,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $contents = null,
        ?string $accessibleLabel = null,
    ): DocumentBuilder {
        return $this->linkToPageWithOptions(
            $pageNumber,
            $x,
            $y,
            $width,
            $height,
            new LinkAnnotationOptions(
                contents: $contents,
                accessibleLabel: $accessibleLabel,
            ),
        );
    }

    public function linkToPageWithOptions(
        int $pageNumber,
        float $x,
        float $y,
        float $width,
        float $height,
        LinkAnnotationOptions $options,
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->currentPageAnnotations[] = $this->buildRectLinkAnnotation(
            LinkTarget::page($pageNumber),
            $x,
            $y,
            $width,
            $height,
            $options,
        );

        return $clone;
    }

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
    ): DocumentBuilder {
        return $this->linkToPagePositionWithOptions(
            $pageNumber,
            $targetX,
            $targetY,
            $x,
            $y,
            $width,
            $height,
            new LinkAnnotationOptions(
                contents: $contents,
                accessibleLabel: $accessibleLabel,
            ),
        );
    }

    public function linkToPagePositionWithOptions(
        int $pageNumber,
        float $targetX,
        float $targetY,
        float $x,
        float $y,
        float $width,
        float $height,
        LinkAnnotationOptions $options,
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->currentPageAnnotations[] = $this->buildRectLinkAnnotation(
            LinkTarget::position($pageNumber, $targetX, $targetY),
            $x,
            $y,
            $width,
            $height,
            $options,
        );

        return $clone;
    }

    public function namedDestination(string $name): DocumentBuilder
    {
        $clone = clone $this;
        $clone->currentPageNamedDestinations[] = NamedDestination::fit($name);

        return $clone;
    }

    public function namedDestinationPosition(string $name, float $x, float $y): DocumentBuilder
    {
        $clone = clone $this;
        $clone->currentPageNamedDestinations[] = NamedDestination::position($name, $x, $y);

        return $clone;
    }

    public function glyphs(StandardFontGlyphRun $glyphRun, ?TextOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= new TextOptions(fontName: $glyphRun->fontName);

        if ($options->fontName !== $glyphRun->fontName) {
            throw new InvalidArgumentException(sprintf(
                "Glyph run font '%s' does not match text option font '%s'.",
                $glyphRun->fontName,
                $options->fontName,
            ));
        }

        $font = StandardFontDefinition::from($glyphRun->fontName);
        $fontEncoding = $font->resolveEncoding(
            ($this->profile ?? Profile::standard())->version(),
            $options->fontEncoding,
        );
        $fontAlias = $clone->fontAliasFor($font->name, $fontEncoding, $glyphRun->differences);
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($options, $font);
        $availableWidth = $textFlow->availableTextWidthFrom($placement['x'], $options);
        /** @var list<string> $measurableGlyphNames */
        $measurableGlyphNames = array_values(array_filter(
            $glyphRun->glyphNames,
            static fn (?string $glyphName): bool => $glyphName !== null,
        ));
        $glyphWidth = StandardFontMetrics::measureGlyphNamesWidth($font->name, $measurableGlyphNames, $options->fontSize)
            ?? 0.0;
        $alignedX = $this->alignedLineX($options->align, $placement['x'], max($availableWidth - $glyphWidth, 0.0));

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->textBlockBuilder()->build(
                encodedText: $glyphRun->bytes,
                options: $options,
                x: $alignedX,
                y: $placement['y'],
                fontAlias: $fontAlias,
                font: $font,
                glyphNames: $glyphRun->glyphNames,
                textAdjustments: [],
                useHexString: $glyphRun->useHexString,
            ),
        );
        $clone->currentPageCursorY = $textFlow->nextCursorY($options, $placement['y']);

        return $clone;
    }

    public function newPage(?PageOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $clone->pages[] = $clone->buildCurrentPage();
        $clone->resetCurrentPage($options);

        return $clone;
    }

    public function build(): Document
    {
        return new Document(
            profile: $this->profile ?? Profile::standard(),
            pages: [...$this->pages, $this->buildCurrentPage()],
            title: $this->title,
            author: $this->author,
            subject: $this->subject,
            language: $this->language,
            creator: $this->creator,
            creatorTool: $this->creatorTool,
            pdfaOutputIntent: $this->pdfaOutputIntent,
            encryption: $this->encryption,
            taggedTables: $this->buildTaggedTables(),
        );
    }

    public function contents(): string
    {
        $output = new StringOutput();

        new DocumentRenderer()->write($this->build(), $output);

        return $output->contents();
    }

    public function writeToStream($stream): void
    {
        $output = new StreamOutput($stream);

        new DocumentRenderer()->write($this->build(), $output);
    }

    public function writeToFile(string $path): void
    {
        $output = new FileOutput($path);

        try {
            new DocumentRenderer()->write($this->build(), $output);
            $output->close();
        } catch (Throwable $throwable) {
            unset($output);

            throw $throwable;
        }
    }

    private function buildCurrentPage(): Page
    {
        return new Page(
            size: $this->currentPageSize ?? PageSize::A4(),
            contents: $this->currentPageContents,
            fontResources: $this->currentPageFontResources,
            imageResources: $this->currentPageImageResources,
            images: $this->currentPageImages,
            annotations: $this->currentPageAnnotations,
            namedDestinations: $this->currentPageNamedDestinations,
            margin: $this->currentPageMargin,
            backgroundColor: $this->currentPageBackgroundColor,
            label: $this->currentPageLabel,
            name: $this->currentPageName,
        );
    }

    private function resetCurrentPage(?PageOptions $options): void
    {
        $this->currentPageSize = $this->resolvePageSize($options);
        $this->currentPageContents = '';
        $this->currentPageFontResources = [];
        $this->currentPageImageResources = [];
        $this->currentPageImages = [];
        $this->currentPageAnnotations = [];
        $this->currentPageNamedDestinations = [];
        $this->currentPageMargin = $options !== null
            ? $options->margin ?? $this->defaultPageMargin
            : $this->defaultPageMargin;
        $this->currentPageCursorY = null;
        $this->currentPageBackgroundColor = $options?->backgroundColor;
        $this->currentPageLabel = $options?->label;
        $this->currentPageName = $options?->name;
        $this->currentPageNextMarkedContentId = 0;
    }

    private function startOverflowPage(): void
    {
        $this->pages[] = $this->buildCurrentPage();
        $this->resetCurrentPage(new PageOptions(
            pageSize: $this->currentPageSize,
            margin: $this->currentPageMargin,
        ));
    }

    private function resolvePageSize(?PageOptions $options): ?PageSize
    {
        $pageSize = $options !== null
            ? $options->pageSize ?? $this->defaultPageSize
            : $this->defaultPageSize;

        if ($pageSize === null) {
            return null;
        }

        return match ($options?->orientation) {
            PageOrientation::LANDSCAPE => $pageSize->landscape(),
            PageOrientation::PORTRAIT => $pageSize->portrait(),
            default => $pageSize,
        };
    }

    /**
     * @param list<string> $wrappedLines
     * @param list<list<ShapedTextRun>> $shapedLines
     * @return array{contents: string, annotations: list<PageAnnotation>}
     */
    private function buildWrappedTextContent(
        array $wrappedLines,
        array $shapedLines,
        TextOptions $options,
        TextFlow $textFlow,
        float $x,
        float $y,
        string $fontAlias,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        ?PageFont $embeddedPageFont,
        bool $useHexString,
        float $pdfVersion,
        ?string $markedContentTag = null,
        ?int $markedContentId = null,
    ): array {
        $contents = [];
        $annotations = [];
        $availableWidth = $textFlow->availableTextWidthFrom($x, $options);

        foreach ($shapedLines as $index => $lineRuns) {
            if ($lineRuns === []) {
                continue;
            }

            $runY = $y - ($textFlow->lineHeight($options) * $index);
            $isFirstLineOfParagraph = $this->isFirstLineOfParagraph($wrappedLines, $index);
            $lineBaseX = $textFlow->lineX($x, $options, $isFirstLineOfParagraph);
            $mappedRuns = [];

            foreach ($lineRuns as $run) {
                $mappedRun = $this->fontRunMapper()->map(
                    $run,
                    $font,
                    $options,
                    $pdfVersion,
                    $embeddedPageFont,
                    $useHexString,
                );

                if ($mappedRun->text === '') {
                    continue;
                }

                $mappedRuns[] = $mappedRun;
            }

            if ($mappedRuns === []) {
                continue;
            }

            $lineWidth = $this->lineWidth($mappedRuns);
            $lineIndent = $isFirstLineOfParagraph
                ? max($options->firstLineIndent, 0.0)
                : max($options->hangingIndent, 0.0);
            $lineAvailableWidth = $options->width !== null
                ? max($availableWidth - $lineIndent, 0.0)
                : $textFlow->availableTextWidthFrom($lineBaseX, $options);
            $remainingWidth = max($lineAvailableWidth - $lineWidth, 0.0);
            $runX = $this->alignedLineX($options->align, $lineBaseX, $remainingWidth);

            if ($options->align === TextAlign::JUSTIFY && $this->shouldJustifyLine($wrappedLines, $index)) {
                $mappedRuns = $this->justifyMappedRuns($mappedRuns, $remainingWidth, $options);
            }

            foreach ($mappedRuns as $mappedRun) {
                $textBlockContent = $this->textBlockBuilder()->build(
                    $mappedRun->encodedText,
                    $options,
                    $runX,
                    $runY,
                    $fontAlias,
                    $font,
                    $mappedRun->glyphNames,
                    $mappedRun->textAdjustments,
                    $mappedRun->positionedFragments,
                    $mappedRun->useHexString,
                );

                if ($options->link !== null && $mappedRun->width > 0.0) {
                    $linkResult = $this->buildLinkedTextRunContent(
                        $this->linkTarget($options->link),
                        $this->linkContents($options->link, $mappedRun->text),
                        $this->linkAccessibleLabel($options->link, $mappedRun->text),
                        $textBlockContent,
                        $runX,
                        $runY,
                        $mappedRun->width,
                        $textFlow->lineHeight($options),
                        $font->ascent($options->fontSize),
                        $this->linkGroupKey($options->link),
                    );
                    $textBlockContent = $linkResult['contents'];
                    $annotations[] = $linkResult['annotation'];
                }

                $contents[] = $textBlockContent;
                $runX += $mappedRun->width;
            }
        }

        $contentsString = implode("\n", $contents);

        if ($contentsString !== '' && $markedContentTag !== null && $markedContentId !== null) {
            $contentsString = $this->wrapMarkedContent($markedContentTag, $markedContentId, $contentsString);
        }

        return [
            'contents' => $contentsString,
            'annotations' => $annotations,
        ];
    }

    /**
     * @param list<list<TextSegment>> $wrappedSegmentLines
     * @return array{contents: string, annotations: list<PageAnnotation>}
     */
    private function buildWrappedTextSegmentsContent(
        array $wrappedSegmentLines,
        TextOptions $options,
        TextFlow $textFlow,
        float $x,
        float $y,
        string $fontAlias,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        ?PageFont $embeddedPageFont,
        bool $useHexString,
        float $pdfVersion,
        ?string $markedContentTag = null,
        ?int $markedContentId = null,
    ): array {
        $contents = [];
        $annotations = [];
        $availableWidth = $textFlow->availableTextWidthFrom($x, $options);
        $currentPageIndex = count($this->pages);
        $nextLinkGroupId = 0;
        $continuingLinkGroup = null;

        foreach ($wrappedSegmentLines as $index => $lineSegments) {
            if ($lineSegments === []) {
                $continuingLinkGroup = null;
                continue;
            }

            $runY = $y - ($textFlow->lineHeight($options) * $index);
            $isFirstLineOfParagraph = $this->isFirstSegmentLineOfParagraph($wrappedSegmentLines, $index);
            $lineBaseX = $textFlow->lineX($x, $options, $isFirstLineOfParagraph);
            $lineEntries = [];

            foreach ($lineSegments as $segment) {
                if ($segment->text === '') {
                    continue;
                }

                $segmentOptions = $this->textOptionsWithLink($options, $segment->link);
                $segmentRuns = $this->textShaper()->shape($segment->text, $segmentOptions->baseDirection, $font);

                foreach ($segmentRuns as $run) {
                    $mappedRun = $this->fontRunMapper()->map(
                        $run,
                        $font,
                        $segmentOptions,
                        $pdfVersion,
                        $embeddedPageFont,
                        $useHexString,
                    );

                    if ($mappedRun->text === '') {
                        continue;
                    }

                    $lineEntries[] = [
                        'mappedRun' => $mappedRun,
                        'link' => $segment->link,
                    ];
                }
            }

            if ($lineEntries === []) {
                continue;
            }

            $mappedRuns = array_map(
                static fn (array $entry): MappedTextRun => $entry['mappedRun'],
                $lineEntries,
            );
            $lineWidth = $this->lineWidth($mappedRuns);
            $lineIndent = $isFirstLineOfParagraph
                ? max($options->firstLineIndent, 0.0)
                : max($options->hangingIndent, 0.0);
            $lineAvailableWidth = $options->width !== null
                ? max($availableWidth - $lineIndent, 0.0)
                : $textFlow->availableTextWidthFrom($lineBaseX, $options);
            $remainingWidth = max($lineAvailableWidth - $lineWidth, 0.0);
            $runX = $this->alignedLineX($options->align, $lineBaseX, $remainingWidth);

            if ($options->align === TextAlign::JUSTIFY && $this->shouldJustifySegmentLine($wrappedSegmentLines, $index)) {
                $mappedRuns = $this->justifyMappedRuns($mappedRuns, $remainingWidth, $options);

                foreach ($mappedRuns as $mappedRunIndex => $mappedRun) {
                    $lineEntries[$mappedRunIndex]['mappedRun'] = $mappedRun;
                }
            }

            $renderedEntries = [];

            foreach ($lineEntries as $lineEntry) {
                /** @var MappedTextRun $mappedRun */
                $mappedRun = $lineEntry['mappedRun'];
                /** @var LinkTarget|TextLink|null $link */
                $link = $lineEntry['link'] ?? null;
                $renderedEntries[] = [
                    'mappedRun' => $mappedRun,
                    'link' => $link,
                    'x' => $runX,
                    'textBlockContent' => $this->textBlockBuilder()->build(
                        $mappedRun->encodedText,
                        $options,
                        $runX,
                        $runY,
                        $fontAlias,
                        $font,
                        $mappedRun->glyphNames,
                        $mappedRun->textAdjustments,
                        $mappedRun->positionedFragments,
                        $mappedRun->useHexString,
                    ),
                ];
                $runX += $mappedRun->width;
            }

            $mergedRenderedEntries = $this->mergeRenderedSegmentEntries($renderedEntries);
            $lastLinkedGroupOnLine = null;

            foreach ($mergedRenderedEntries as $renderedEntryIndex => $renderedEntry) {
                /** @var LinkTarget|TextLink|null $link */
                $link = $renderedEntry['link'];
                $textBlockContent = $renderedEntry['textBlockContent'];

                if ($link !== null && $renderedEntry['width'] > 0.0) {
                    $groupKey = $renderedEntryIndex === 0
                        && $continuingLinkGroup !== null
                        && $this->canMergeTextLinks($continuingLinkGroup['link'], $link)
                        ? $continuingLinkGroup['key']
                        : $this->linkGroupKey($link) ?? ('page-' . $currentPageIndex . '-text-link-' . $nextLinkGroupId++);
                    $linkResult = $this->buildLinkedTextRunContent(
                        $this->linkTarget($link),
                        $this->linkContents($link, $renderedEntry['text']),
                        $this->linkAccessibleLabel($link, $renderedEntry['text']),
                        $textBlockContent,
                        $renderedEntry['x'],
                        $runY,
                        $renderedEntry['width'],
                        $textFlow->lineHeight($options),
                        $font->ascent($options->fontSize),
                        $groupKey,
                    );
                    $textBlockContent = $linkResult['contents'];
                    $annotations[] = $linkResult['annotation'];
                    $lastLinkedGroupOnLine = [
                        'key' => $groupKey,
                        'link' => $link,
                    ];
                } else {
                    $lastLinkedGroupOnLine = null;
                }

                $contents[] = $textBlockContent;
            }

            $continuingLinkGroup = $lastLinkedGroupOnLine;
        }

        $contentsString = implode("\n", $contents);

        if ($contentsString !== '' && $markedContentTag !== null && $markedContentId !== null) {
            $contentsString = $this->wrapMarkedContent($markedContentTag, $markedContentId, $contentsString);
        }

        return [
            'contents' => $contentsString,
            'annotations' => $annotations,
        ];
    }

    /**
     * @param list<array{mappedRun: MappedTextRun, link: LinkTarget|TextLink|null, x: float, textBlockContent: string}> $renderedEntries
     * @return list<array{link: LinkTarget|TextLink|null, x: float, width: float, text: string, textBlockContent: string}>
     */
    private function mergeRenderedSegmentEntries(array $renderedEntries): array
    {
        $mergedEntries = [];

        foreach ($renderedEntries as $renderedEntry) {
            /** @var MappedTextRun $mappedRun */
            $mappedRun = $renderedEntry['mappedRun'];
            /** @var LinkTarget|TextLink|null $link */
            $link = $renderedEntry['link'];
            $lastIndex = array_key_last($mergedEntries);

            if (
                $lastIndex !== null
                && $link !== null
                && $mergedEntries[$lastIndex]['link'] !== null
                && $this->canMergeTextLinks($mergedEntries[$lastIndex]['link'], $link)
            ) {
                $mergedEntries[$lastIndex]['width'] += $mappedRun->width;
                $mergedEntries[$lastIndex]['text'] .= $mappedRun->text;
                $mergedEntries[$lastIndex]['textBlockContent'] .= "\n" . $renderedEntry['textBlockContent'];

                continue;
            }

            $mergedEntries[] = [
                'link' => $link,
                'x' => $renderedEntry['x'],
                'width' => $mappedRun->width,
                'text' => $mappedRun->text,
                'textBlockContent' => $renderedEntry['textBlockContent'],
            ];
        }

        return $mergedEntries;
    }

    private function canMergeTextLinks(LinkTarget | TextLink $left, LinkTarget | TextLink $right): bool
    {
        $leftGroupKey = $this->linkGroupKey($left);
        $rightGroupKey = $this->linkGroupKey($right);

        if ($leftGroupKey !== null || $rightGroupKey !== null) {
            return $leftGroupKey !== null
                && $rightGroupKey !== null
                && $leftGroupKey === $rightGroupKey;
        }

        return $this->sameLinkTarget($this->linkTarget($left), $this->linkTarget($right));
    }

    private function sameLinkTarget(LinkTarget $left, LinkTarget $right): bool
    {
        if ($left->isExternalUrl() && $right->isExternalUrl()) {
            return $left->externalUrlValue() === $right->externalUrlValue();
        }

        if ($left->isNamedDestination() && $right->isNamedDestination()) {
            return $left->namedDestinationValue() === $right->namedDestinationValue();
        }

        if ($left->isPage() && $right->isPage()) {
            return $left->pageNumberValue() === $right->pageNumberValue();
        }

        if ($left->isPosition() && $right->isPosition()) {
            return $left->pageNumberValue() === $right->pageNumberValue()
                && $left->xValue() === $right->xValue()
                && $left->yValue() === $right->yValue();
        }

        return false;
    }

    /**
     * @param list<MappedTextRun> $mappedRuns
     */
    private function lineWidth(array $mappedRuns): float
    {
        $width = 0.0;

        foreach ($mappedRuns as $mappedRun) {
            $width += $mappedRun->width;
        }

        return $width;
    }

    private function alignedLineX(TextAlign $align, float $x, float $remainingWidth): float
    {
        return match ($align) {
            TextAlign::CENTER => $x + ($remainingWidth / 2),
            TextAlign::RIGHT => $x + $remainingWidth,
            default => $x,
        };
    }

    /**
     * @param list<string> $wrappedLines
     */
    private function shouldJustifyLine(array $wrappedLines, int $lineIndex): bool
    {
        if (!isset($wrappedLines[$lineIndex + 1])) {
            return false;
        }

        return $wrappedLines[$lineIndex] !== '' && $wrappedLines[$lineIndex + 1] !== '';
    }

    /**
     * @param list<list<TextSegment>> $wrappedLines
     */
    private function shouldJustifySegmentLine(array $wrappedLines, int $lineIndex): bool
    {
        if (!isset($wrappedLines[$lineIndex + 1])) {
            return false;
        }

        return $wrappedLines[$lineIndex] !== [] && $wrappedLines[$lineIndex + 1] !== [];
    }

    /**
     * @param list<string> $wrappedLines
     */
    private function isFirstLineOfParagraph(array $wrappedLines, int $lineIndex): bool
    {
        if ($lineIndex === 0) {
            return true;
        }

        return ($wrappedLines[$lineIndex - 1] ?? '') === '';
    }

    /**
     * @param list<list<TextSegment>> $wrappedLines
     */
    private function isFirstSegmentLineOfParagraph(array $wrappedLines, int $lineIndex): bool
    {
        if ($lineIndex === 0) {
            return true;
        }

        return ($wrappedLines[$lineIndex - 1] ?? []) === [];
    }

    /**
     * @param list<MappedTextRun> $mappedRuns
     * @return list<MappedTextRun>
     */
    private function justifyMappedRuns(array $mappedRuns, float $remainingWidth, TextOptions $options): array
    {
        if ($remainingWidth <= 0.0) {
            return $mappedRuns;
        }

        $spaceCount = 0;

        foreach ($mappedRuns as $mappedRun) {
            $spaceCount += substr_count($mappedRun->text, ' ');
        }

        if ($spaceCount === 0) {
            return $mappedRuns;
        }

        $extraSpaceAdjustment = (int) round(-($remainingWidth / $spaceCount) / $options->fontSize * 1000);
        $justifiedRuns = [];

        foreach ($mappedRuns as $mappedRun) {
            if ($mappedRun->positionedFragments !== []) {
                $justifiedRuns[] = $mappedRun;

                continue;
            }

            $adjustments = $mappedRun->textAdjustments;
            $characters = preg_split('//u', $mappedRun->text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($mappedRun->text);

            foreach ($characters as $index => $character) {
                if ($character !== ' ' || !isset($characters[$index + 1])) {
                    continue;
                }

                $adjustments[$index] = ($adjustments[$index] ?? 0) + $extraSpaceAdjustment;
            }

            $justifiedRuns[] = new MappedTextRun(
                script: $mappedRun->script,
                text: $mappedRun->text,
                encodedText: $mappedRun->encodedText,
                glyphNames: $mappedRun->glyphNames,
                codePoints: $mappedRun->codePoints,
                embeddedGlyphs: $mappedRun->embeddedGlyphs,
                textAdjustments: array_values($adjustments),
                positionedFragments: $mappedRun->positionedFragments,
                useHexString: $mappedRun->useHexString,
                width: $mappedRun->width + ($remainingWidth * (substr_count($mappedRun->text, ' ') / $spaceCount)),
            );
        }

        return $justifiedRuns;
    }

    private function appendPageContent(string $existingContent, string $newContent): string
    {
        if ($existingContent === '') {
            return $newContent;
        }

        return $existingContent . "\n" . $newContent;
    }

    private function wrapMarkedContent(string $tag, int $markedContentId, string $contents): string
    {
        return implode("\n", [
            '/' . $tag . ' << /MCID ' . $markedContentId . ' >> BDC',
            $contents,
            'EMC',
        ]);
    }

    /**
     * @return array{wrappedLines: list<string>, textOptions: TextOptions, height: float}
     */
    private function layoutTableCaption(
        Table $table,
        TextFlow $textFlow,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        float $tableWidth,
    ): array {
        $caption = $table->caption;

        if ($caption === null) {
            throw new InvalidArgumentException('Cannot layout a missing table caption.');
        }

        $textOptions = $this->tableCaptionTextOptions($caption, $table->textOptions, $tableWidth);
        $wrappedLines = $textFlow->wrapTextLines($caption->text, $textOptions, $font, 0.0);

        return [
            'wrappedLines' => $wrappedLines,
            'textOptions' => $textOptions,
            'height' => (max(count($wrappedLines), 1) * $textFlow->lineHeight($textOptions)) + $caption->spacingAfter,
        ];
    }

    /**
     * @param array{wrappedLines: list<string>, textOptions: TextOptions, height: float} $captionLayout
     */
    private function renderTableCaption(
        array $captionLayout,
        TableCaption $caption,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        float $topY,
        float $leftX,
        ?int $taggedTableId = null,
    ): void {
        $shapedLines = $this->shapeWrappedTextLines($captionLayout['wrappedLines'], $captionLayout['textOptions'], $font);
        $renderState = $this->prepareTextRenderState($caption->text, $captionLayout['textOptions'], $font, $shapedLines);
        $markedContentId = $taggedTableId !== null ? $this->nextMarkedContentId() : null;
        $textResult = $this->buildWrappedTextContent(
            $captionLayout['wrappedLines'],
            $shapedLines,
            $captionLayout['textOptions'],
            new TextFlow($this->buildCurrentPage()),
            $leftX,
            $topY - $font->ascent($captionLayout['textOptions']->fontSize),
            $renderState['fontAlias'],
            $font,
            $renderState['embeddedPageFont'],
            $renderState['useHexString'],
            ($this->profile ?? Profile::standard())->version(),
            $markedContentId !== null ? 'Caption' : null,
            $markedContentId,
        );

        $this->currentPageContents = $this->appendPageContent($this->currentPageContents, $textResult['contents']);
        $this->currentPageAnnotations = [...$this->currentPageAnnotations, ...$textResult['annotations']];

        if ($taggedTableId !== null) {
            $this->addTaggedTableCaptionReference($taggedTableId, $markedContentId);
        }
    }

    /**
     * @return array{contents: string, annotation: LinkAnnotation}
     */
    private function buildLinkedTextRunContent(
        LinkTarget $link,
        string $contents,
        string $accessibleLabel,
        string $textBlockContent,
        float $x,
        float $y,
        float $width,
        float $lineHeight,
        float $ascent,
        ?string $taggedGroupKey = null,
    ): array {
        $markedContentId = ($this->profile ?? Profile::standard())->requiresTaggedLinkAnnotations()
            ? $this->nextMarkedContentId()
            : null;

        if ($markedContentId !== null) {
            $textBlockContent = implode("\n", [
                '/Link << /MCID ' . $markedContentId . ' >> BDC',
                $textBlockContent,
                'EMC',
            ]);
        }

        return [
            'contents' => $textBlockContent,
            'annotation' => new LinkAnnotation(
                target: $link,
                x: $x,
                y: $y - max($lineHeight - $ascent, 0.0),
                width: $width,
                height: $lineHeight,
                contents: $contents,
                accessibleLabel: $accessibleLabel,
                markedContentId: $markedContentId,
                taggedGroupKey: $taggedGroupKey,
            ),
        ];
    }

    private function textOptionsWithLink(TextOptions $options, LinkTarget | TextLink | null $link): TextOptions
    {
        return new TextOptions(
            x: $options->x,
            y: $options->y,
            width: $options->width,
            maxWidth: $options->maxWidth,
            fontSize: $options->fontSize,
            lineHeight: $options->lineHeight,
            spacingBefore: $options->spacingBefore,
            spacingAfter: $options->spacingAfter,
            fontName: $options->fontName,
            embeddedFont: $options->embeddedFont,
            fontEncoding: $options->fontEncoding,
            color: $options->color,
            kerning: $options->kerning,
            baseDirection: $options->baseDirection,
            align: $options->align,
            firstLineIndent: $options->firstLineIndent,
            hangingIndent: $options->hangingIndent,
            link: $link,
        );
    }

    private function linkTarget(LinkTarget | TextLink $link): LinkTarget
    {
        return $link instanceof TextLink ? $link->target : $link;
    }

    private function linkContents(LinkTarget | TextLink $link, string $visibleText): string
    {
        if ($link instanceof TextLink && $link->contents !== null) {
            return $link->contents;
        }

        return $visibleText;
    }

    private function linkAccessibleLabel(LinkTarget | TextLink $link, string $visibleText): string
    {
        if ($link instanceof TextLink && $link->accessibleLabel !== null) {
            return $link->accessibleLabel;
        }

        if ($link instanceof TextLink && $link->contents !== null) {
            return $link->contents;
        }

        return $visibleText;
    }

    private function linkGroupKey(LinkTarget | TextLink $link): ?string
    {
        return $link instanceof TextLink ? $link->groupKey : null;
    }

    private function buildRectLinkAnnotation(
        LinkTarget $target,
        float $x,
        float $y,
        float $width,
        float $height,
        LinkAnnotationOptions $options,
    ): LinkAnnotation {
        $metadata = $options->metadata();

        return new LinkAnnotation(
            target: $target,
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            contents: $metadata->contents,
            accessibleLabel: $metadata->accessibleLabel,
            taggedGroupKey: $metadata->groupKey,
        );
    }

    private function buildTableCellContent(
        TableLayout $tableLayout,
        TableCellLayout $cellLayout,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        TextFlow $textFlow,
        float $segmentTopY,
        int $groupStartRowIndex,
        float $segmentOffset,
        float $segmentHeight,
        float $tableLeftX,
        ?int $taggedTableId = null,
        ?string $taggedSection = null,
    ): string {
        $contents = [];
        $x = $tableLeftX;
        $cellTopOffset = 0.0;

        for ($index = $groupStartRowIndex; $index < $cellLayout->rowIndex; $index++) {
            $cellTopOffset += $tableLayout->rowHeights[$index];
        }

        for ($index = 0; $index < $cellLayout->columnIndex; $index++) {
            $x += $tableLayout->columnWidths[$index];
        }

        $shapedLines = $cellLayout->usesRichText()
            ? []
            : $this->shapeWrappedTextLines($cellLayout->wrappedLines, $cellLayout->textOptions, $font);
        $renderState = $this->prepareTextRenderState($cellLayout->cell->text, $cellLayout->textOptions, $font, $shapedLines);
        $cellHeight = $tableLayout->cellHeight($cellLayout);
        $cellBottomOffset = $cellTopOffset + $cellHeight;
        $segmentBottomOffset = $segmentOffset + $segmentHeight;
        $visibleTopOffset = max($cellTopOffset, $segmentOffset);
        $visibleBottomOffset = min($cellBottomOffset, $segmentBottomOffset);

        if ($visibleBottomOffset <= $visibleTopOffset) {
            return '';
        }

        $visibleHeight = $visibleBottomOffset - $visibleTopOffset;
        $topY = $segmentTopY - ($visibleTopOffset - $segmentOffset);
        $rendersCellTop = $visibleTopOffset <= $cellTopOffset;
        $rendersCellBottom = $visibleBottomOffset >= $cellBottomOffset;

        if ($cellLayout->cell->backgroundColor !== null) {
            $contents[] = $this->buildCellBackgroundContent(
                $x,
                $topY,
                $cellLayout->width,
                $visibleHeight,
                $cellLayout->cell->backgroundColor,
            );
        }

        $markedContentId = $taggedTableId !== null && $taggedSection !== null
            ? $this->nextMarkedContentId()
            : null;
        $segmentText = $this->visibleWrappedTextContentForCellSegment(
            $cellLayout,
            $cellHeight,
            $shapedLines,
            $font,
            $renderState['fontAlias'],
            $renderState['embeddedPageFont'],
            $renderState['useHexString'],
            $textFlow,
            $x + $cellLayout->padding->left,
            $segmentTopY,
            $cellTopOffset,
            $segmentOffset,
            $segmentHeight,
            $markedContentId !== null ? ($taggedSection === 'header' ? 'TH' : 'TD') : null,
            $markedContentId,
        );

        if ($cellLayout->border->isVisible()) {
            $contents[] = $this->buildCellBorderSegmentContent(
                $x,
                $topY,
                $cellLayout->width,
                $visibleHeight,
                $rendersCellTop,
                $rendersCellBottom,
                $cellLayout->border,
            );
        }

        if ($segmentText !== null) {
            if ($segmentText['contents'] !== '') {
                $contents[] = $segmentText['contents'];
            }

            $this->currentPageAnnotations = [...$this->currentPageAnnotations, ...$segmentText['annotations']];

            if ($taggedTableId !== null && $taggedSection !== null && $markedContentId !== null) {
                $this->addTaggedTableCellReference(
                    $taggedTableId,
                    $taggedSection,
                    $cellLayout->rowIndex,
                    $cellLayout->columnIndex,
                    $markedContentId,
                );
            }
        }

        return implode("\n", array_filter($contents, static fn (string $content): bool => $content !== ''));
    }

    /**
     * @param list<list<ShapedTextRun>> $shapedLines
     * @return array{contents: string, annotations: list<PageAnnotation>}|null
     */
    private function visibleWrappedTextContentForCellSegment(
        TableCellLayout $cellLayout,
        float $cellHeight,
        array $shapedLines,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        string $fontAlias,
        ?PageFont $embeddedPageFont,
        bool $useHexString,
        TextFlow $textFlow,
        float $x,
        float $segmentTopY,
        float $cellTopOffset,
        float $segmentOffset,
        float $segmentHeight,
        ?string $markedContentTag = null,
        ?int $markedContentId = null,
    ): ?array {
        if ($cellLayout->usesRichText()) {
            return $this->visibleWrappedTextSegmentsContentForCellSegment(
                $cellLayout,
                $font,
                $fontAlias,
                $embeddedPageFont,
                $useHexString,
                $textFlow,
                $x,
                $segmentTopY,
                $cellTopOffset,
                $segmentOffset,
                $segmentHeight,
                $markedContentTag,
                $markedContentId,
            );
        }

        $cellTextOptions = $cellLayout->textOptions;
        $lineHeight = $textFlow->lineHeight($cellTextOptions);
        $textTopOffset = $cellTopOffset + $this->tableCellVerticalOffset(
            $cellHeight,
            $cellLayout,
            $cellLayout->padding,
            $cellTextOptions,
        );
        $firstLineBaselineOffset = $textTopOffset + $font->ascent($cellTextOptions->fontSize);
        $segmentBottomOffset = $segmentOffset + $segmentHeight;
        $visibleLineIndexes = [];

        foreach ($cellLayout->wrappedLines as $index => $_line) {
            $lineTopOffset = $textTopOffset + ($lineHeight * $index);

            if ($lineTopOffset < $segmentOffset || $lineTopOffset >= $segmentBottomOffset) {
                continue;
            }

            $visibleLineIndexes[] = $index;
        }

        if ($visibleLineIndexes === []) {
            return null;
        }

        $startIndex = $visibleLineIndexes[0];
        $visibleWrappedLines = [];
        $visibleShapedLines = [];

        foreach ($visibleLineIndexes as $index) {
            $visibleWrappedLines[] = $cellLayout->wrappedLines[$index];
            $visibleShapedLines[] = $shapedLines[$index];
        }

        $firstLineY = $segmentTopY - (($firstLineBaselineOffset + ($lineHeight * $startIndex)) - $segmentOffset);

        return $this->buildWrappedTextContent(
            $visibleWrappedLines,
            $visibleShapedLines,
            $cellTextOptions,
            $textFlow,
            $x,
            $firstLineY,
            $fontAlias,
            $font,
            $embeddedPageFont,
            $useHexString,
            ($this->profile ?? Profile::standard())->version(),
            $markedContentTag,
            $markedContentId,
        );
    }

    /**
     * @return array{contents: string, annotations: list<PageAnnotation>}|null
     */
    private function visibleWrappedTextSegmentsContentForCellSegment(
        TableCellLayout $cellLayout,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        string $fontAlias,
        ?PageFont $embeddedPageFont,
        bool $useHexString,
        TextFlow $textFlow,
        float $x,
        float $segmentTopY,
        float $cellTopOffset,
        float $segmentOffset,
        float $segmentHeight,
        ?string $markedContentTag = null,
        ?int $markedContentId = null,
    ): ?array {
        $wrappedSegmentLines = $cellLayout->wrappedSegmentLines;

        if ($wrappedSegmentLines === null) {
            return null;
        }

        $cellTextOptions = $cellLayout->textOptions;
        $lineHeight = $textFlow->lineHeight($cellTextOptions);
        $textTopOffset = $cellTopOffset + $this->tableCellVerticalOffset(
            $cellLayout->height,
            $cellLayout,
            $cellLayout->padding,
            $cellTextOptions,
        );
        $firstLineBaselineOffset = $textTopOffset + $font->ascent($cellTextOptions->fontSize);
        $segmentBottomOffset = $segmentOffset + $segmentHeight;
        $visibleLineIndexes = [];

        foreach ($wrappedSegmentLines as $index => $_line) {
            $lineTopOffset = $textTopOffset + ($lineHeight * $index);

            if ($lineTopOffset < $segmentOffset || $lineTopOffset >= $segmentBottomOffset) {
                continue;
            }

            $visibleLineIndexes[] = $index;
        }

        if ($visibleLineIndexes === []) {
            return null;
        }

        $startIndex = $visibleLineIndexes[0];
        $visibleSegmentLines = [];

        foreach ($visibleLineIndexes as $index) {
            $visibleSegmentLines[] = $wrappedSegmentLines[$index];
        }

        $firstLineY = $segmentTopY - (($firstLineBaselineOffset + ($lineHeight * $startIndex)) - $segmentOffset);

        return $this->buildWrappedTextSegmentsContent(
            $visibleSegmentLines,
            $cellTextOptions,
            $textFlow,
            $x,
            $firstLineY,
            $fontAlias,
            $font,
            $embeddedPageFont,
            $useHexString,
            ($this->profile ?? Profile::standard())->version(),
            $markedContentTag,
            $markedContentId,
        );
    }

    private function tableCellVerticalOffset(
        float $cellHeight,
        TableCellLayout $cellLayout,
        CellPadding $cellPadding,
        TextOptions $textOptions,
    ): float {
        $textHeight = max($cellLayout->lineCount(), 1) * ($textOptions->lineHeight ?? ($textOptions->fontSize * 1.2));
        $contentHeight = max($cellHeight - $cellPadding->vertical(), 0.0);
        $availableSpace = max($contentHeight - $textHeight, 0.0);

        return match ($cellLayout->cell->verticalAlign) {
            VerticalAlign::MIDDLE => $cellPadding->top + ($availableSpace / 2),
            VerticalAlign::BOTTOM => $cellPadding->top + $availableSpace,
            VerticalAlign::TOP => $cellPadding->top,
        };
    }

    private function buildCellBackgroundContent(
        float $x,
        float $topY,
        float $width,
        float $height,
        Color $backgroundColor,
    ): string {
        return implode("\n", [
            'q',
            $this->buildFillColorOperator($backgroundColor),
            $this->formatNumber($x) . ' ' . $this->formatNumber($topY - $height) . ' '
            . $this->formatNumber($width) . ' ' . $this->formatNumber($height) . ' re',
            'f',
            'Q',
        ]);
    }

    private function renderTableLayout(
        Table $table,
        TableLayout $tableLayout,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        float $topY,
        float $leftX,
        ?int $taggedTableId = null,
        ?string $taggedSection = null,
    ): void {
        $contents = [];

        foreach ($tableLayout->cells as $cellLayout) {
            $contents[] = $this->buildTableCellContent(
                $tableLayout,
                $cellLayout,
                $font,
                new TextFlow($this->buildCurrentPage()),
                $topY,
                0,
                0.0,
                $tableLayout->totalHeight(),
                $leftX,
                $taggedTableId,
                $taggedSection,
            );
        }

        $this->currentPageContents = $this->appendPageContent(
            $this->currentPageContents,
            implode("\n", array_filter($contents, static fn (string $content): bool => $content !== '')),
        );
    }

    private function renderTableRowGroupSegment(
        Table $table,
        TableLayout $tableLayout,
        TableRowGroupLayout $rowGroup,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        float $topY,
        float $leftX,
        float $segmentOffset,
        float $segmentHeight,
        ?int $taggedTableId = null,
        ?string $taggedSection = null,
    ): void {
        $contents = [];

        foreach ($tableLayout->cells as $cellLayout) {
            if ($cellLayout->rowIndex < $rowGroup->startRowIndex || $cellLayout->rowIndex > $rowGroup->endRowIndex) {
                continue;
            }

            $contents[] = $this->buildTableCellContent(
                $tableLayout,
                $cellLayout,
                $font,
                new TextFlow($this->buildCurrentPage()),
                $topY,
                $rowGroup->startRowIndex,
                $segmentOffset,
                $segmentHeight,
                $leftX,
                $taggedTableId,
                $taggedSection,
            );
        }

        $this->currentPageContents = $this->appendPageContent(
            $this->currentPageContents,
            implode("\n", array_filter($contents, static fn (string $content): bool => $content !== '')),
        );
    }

    private function registerTaggedTable(
        ?TableLayout $headerLayout,
        TableLayout $bodyLayout,
        ?TableLayout $footerLayout,
    ): int {
        $tableId = $this->nextTaggedTableId;
        $this->nextTaggedTableId++;
        $this->taggedTables[$tableId] = [
            'captionReferences' => [],
            'headerRows' => $headerLayout !== null ? $this->initializeTaggedTableRows($headerLayout, true) : [],
            'bodyRows' => $this->initializeTaggedTableRows($bodyLayout, false),
            'footerRows' => $footerLayout !== null ? $this->initializeTaggedTableRows($footerLayout, false) : [],
        ];

        return $tableId;
    }

    /**
     * @return array<int, array{cells: array<int, array{header: bool, headerScope: ?TableHeaderScope, rowspan: int, colspan: int, references: list<array{pageIndex: int, markedContentId: int}>}>}>
     */
    private function initializeTaggedTableRows(TableLayout $tableLayout, bool $header): array
    {
        $rows = [];

        foreach ($tableLayout->rowHeights as $rowIndex => $_rowHeight) {
            $rows[$rowIndex] = ['cells' => []];
        }

        foreach ($tableLayout->cells as $cellLayout) {
            $rows[$cellLayout->rowIndex]['cells'][$cellLayout->columnIndex] = [
                'header' => $header || $cellLayout->cell->headerScope !== null,
                'headerScope' => $cellLayout->cell->headerScope ?? ($header ? TableHeaderScope::COLUMN : null),
                'rowspan' => $cellLayout->cell->rowspan,
                'colspan' => $cellLayout->cell->colspan,
                'references' => [],
            ];
        }

        return $rows;
    }

    private function addTaggedTableCaptionReference(int $tableId, int $markedContentId): void
    {
        $this->taggedTables[$tableId]['captionReferences'][] = [
            'pageIndex' => count($this->pages),
            'markedContentId' => $markedContentId,
        ];
    }

    private function addTaggedTableCellReference(
        int $tableId,
        string $section,
        int $rowIndex,
        int $columnIndex,
        int $markedContentId,
    ): void {
        $sectionKey = match ($section) {
            'header' => 'headerRows',
            'footer' => 'footerRows',
            default => 'bodyRows',
        };
        $this->taggedTables[$tableId][$sectionKey][$rowIndex]['cells'][$columnIndex]['references'][] = [
            'pageIndex' => count($this->pages),
            'markedContentId' => $markedContentId,
        ];
    }

    /**
     * @return list<TaggedTable>
     */
    private function buildTaggedTables(): array
    {
        $taggedTables = [];

        foreach ($this->taggedTables as $tableId => $table) {
            $taggedTables[] = new TaggedTable(
                tableId: $tableId,
                captionReferences: array_map(
                    static fn (array $reference): TaggedTableContentReference => new TaggedTableContentReference(
                        $reference['pageIndex'],
                        $reference['markedContentId'],
                    ),
                    $table['captionReferences'],
                ),
                headerRows: $this->buildTaggedTableRows($table['headerRows']),
                bodyRows: $this->buildTaggedTableRows($table['bodyRows']),
                footerRows: $this->buildTaggedTableRows($table['footerRows']),
            );
        }

        return $taggedTables;
    }

    /**
     * @param array<int, array{cells: array<int, array{header: bool, headerScope: ?TableHeaderScope, rowspan: int, colspan: int, references: list<array{pageIndex: int, markedContentId: int}>}>}> $rows
     * @return list<TaggedTableRow>
     */
    private function buildTaggedTableRows(array $rows): array
    {
        $taggedRows = [];

        foreach ($rows as $rowIndex => $row) {
            ksort($row['cells']);
            $taggedCells = [];

            foreach ($row['cells'] as $columnIndex => $cell) {
                $taggedCells[] = new TaggedTableCell(
                    columnIndex: $columnIndex,
                    header: $cell['header'],
                    headerScope: $cell['headerScope'],
                    rowspan: $cell['rowspan'],
                    colspan: $cell['colspan'],
                    contentReferences: array_map(
                        static fn (array $reference): TaggedTableContentReference => new TaggedTableContentReference(
                            $reference['pageIndex'],
                            $reference['markedContentId'],
                        ),
                        $cell['references'],
                    ),
                );
            }

            $taggedRows[] = new TaggedTableRow($rowIndex, $taggedCells);
        }

        return $taggedRows;
    }

    private function tableCaptionTextOptions(TableCaption $caption, TextOptions $baseOptions, float $contentWidth): TextOptions
    {
        $captionOptions = $caption->textOptions ?? $baseOptions;

        return new TextOptions(
            width: $contentWidth,
            fontSize: $captionOptions->fontSize,
            lineHeight: $captionOptions->lineHeight,
            fontName: $captionOptions->fontName,
            embeddedFont: $captionOptions->embeddedFont,
            fontEncoding: $captionOptions->fontEncoding,
            color: $captionOptions->color,
            kerning: $captionOptions->kerning,
            baseDirection: $captionOptions->baseDirection,
            align: $captionOptions->align,
            firstLineIndent: $captionOptions->firstLineIndent,
            hangingIndent: $captionOptions->hangingIndent,
            link: $captionOptions->link,
        );
    }

    private function lineHeightForTable(Table $table): float
    {
        return $table->textOptions->lineHeight ?? ($table->textOptions->fontSize * 1.2);
    }

    /**
     * @return array{x: float, width: float}
     */
    private function resolveTablePlacement(Table $table, Page $page): array
    {
        $contentArea = $page->contentArea();

        if ($table->placement === null) {
            return [
                'x' => $contentArea->left,
                'width' => $contentArea->width(),
            ];
        }

        if ($table->placement->x < $contentArea->left) {
            throw new InvalidArgumentException('Table placement x must not start left of the page content area.');
        }

        if (($table->placement->x + $table->placement->width) > $contentArea->right) {
            throw new InvalidArgumentException('Table placement width exceeds the page content area.');
        }

        if ($table->placement->y !== null && ($table->placement->y > $contentArea->top || $table->placement->y < $contentArea->bottom)) {
            throw new InvalidArgumentException('Table placement y must stay within the page content area.');
        }

        return [
            'x' => $table->placement->x,
            'width' => $table->placement->width,
        ];
    }

    private function nextTableCursorY(Table $table, Page $page, float $tableBottomY): float
    {
        if ($table->placement?->y === null) {
            return $tableBottomY;
        }

        return min($this->currentPageCursorY ?? $page->contentArea()->top, $tableBottomY);
    }

    /**
     * @param list<list<ShapedTextRun>> $shapedLines
     * @return array{fontAlias: string, embeddedPageFont: ?PageFont, useHexString: bool}
     */
    private function prepareTextRenderState(
        string $text,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        array $shapedLines,
    ): array {
        $usesUnicodeEmbeddedFont = $font instanceof EmbeddedFontDefinition
            && (
                !$font->supportsText($text)
                || $this->containsShapedEmbeddedGlyphIds($shapedLines)
            );

        if ($font instanceof EmbeddedFontDefinition && $usesUnicodeEmbeddedFont && !$font->supportsUnicodeText($text)) {
            throw new InvalidArgumentException(sprintf(
                "Text cannot be encoded with embedded font '%s'.",
                $font->metadata->postScriptName,
            ));
        }

        $fontAlias = $font instanceof EmbeddedFontDefinition
            ? (
                $usesUnicodeEmbeddedFont
                    ? $this->embeddedUnicodeFontAliasFor($font, $this->embeddedGlyphsForShapedLines($shapedLines, $font))
                    : $this->embeddedFontAliasFor($font)
            )
            : $this->fontAliasFor(
                $font->name,
                $font->resolveEncoding(
                    ($this->profile ?? Profile::standard())->version(),
                    $options->fontEncoding,
                ),
            );

        return [
            'fontAlias' => $fontAlias,
            'embeddedPageFont' => $font instanceof EmbeddedFontDefinition
                ? $this->currentPageFontResources[$fontAlias] ?? null
                : null,
            'useHexString' => $usesUnicodeEmbeddedFont,
        ];
    }

    private function buildCellBorderSegmentContent(
        float $x,
        float $topY,
        float $width,
        float $height,
        bool $renderTopBorder,
        bool $renderBottomBorder,
        Border $border,
    ): string {
        $segments = [];
        $rightX = $x + $width;
        $bottomY = $topY - $height;

        if ($renderTopBorder && $border->top > 0.0) {
            $segments[] = $this->buildStrokeLineContent($x, $topY, $rightX, $topY, $border->top);
        }

        if ($border->right > 0.0) {
            $segments[] = $this->buildStrokeLineContent($rightX, $topY, $rightX, $bottomY, $border->right);
        }

        if ($renderBottomBorder && $border->bottom > 0.0) {
            $segments[] = $this->buildStrokeLineContent($x, $bottomY, $rightX, $bottomY, $border->bottom);
        }

        if ($border->left > 0.0) {
            $segments[] = $this->buildStrokeLineContent($x, $topY, $x, $bottomY, $border->left);
        }

        return implode("\n", $segments);
    }

    private function buildStrokeLineContent(float $x1, float $y1, float $x2, float $y2, float $width): string
    {
        return implode("\n", [
            'q',
            $this->formatNumber($width) . ' w',
            $this->formatNumber($x1) . ' ' . $this->formatNumber($y1) . ' m',
            $this->formatNumber($x2) . ' ' . $this->formatNumber($y2) . ' l',
            'S',
            'Q',
        ]);
    }

    private function buildFillColorOperator(Color $color): string
    {
        $components = array_map(
            fn (float $value): string => $this->formatNumber($value),
            $color->components(),
        );

        return match ($color->space) {
            ColorSpace::GRAY => implode(' ', $components) . ' g',
            ColorSpace::RGB => implode(' ', $components) . ' rg',
            ColorSpace::CMYK => implode(' ', $components) . ' k',
        };
    }

    private function buildImageContent(
        string $imageAlias,
        float $x,
        float $y,
        float $width,
        float $height,
        ?ImageAccessibility $accessibility = null,
        ?int $markedContentId = null,
    ): string {
        $lines = [
            'q',
            $this->formatNumber($width) . ' 0 0 ' . $this->formatNumber($height) . ' '
            . $this->formatNumber($x) . ' ' . $this->formatNumber($y) . ' cm',
            '/' . $imageAlias . ' Do',
            'Q',
        ];

        if (!($this->profile ?? Profile::standard())->requiresTaggedPdf()) {
            return implode("\n", $lines);
        }

        if ($accessibility?->decorative === true) {
            return implode("\n", [
                '/Artifact BMC',
                ...$lines,
                'EMC',
            ]);
        }

        if ($markedContentId === null) {
            return implode("\n", $lines);
        }

        return implode("\n", [
            '/Figure << /MCID ' . $markedContentId . ' >> BDC',
            ...$lines,
            'EMC',
        ]);
    }

    private function markedContentIdForImage(?ImageAccessibility $accessibility): ?int
    {
        if (!($this->profile ?? Profile::standard())->requiresTaggedPdf()) {
            return null;
        }

        if ($accessibility?->decorative === true) {
            return null;
        }

        $markedContentId = $this->currentPageNextMarkedContentId;
        $this->currentPageNextMarkedContentId++;

        return $markedContentId;
    }

    private function nextMarkedContentId(): int
    {
        $markedContentId = $this->currentPageNextMarkedContentId;
        $this->currentPageNextMarkedContentId++;

        return $markedContentId;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveImageDimensions(ImageSource $source, ImagePlacement $placement): array
    {
        if ($placement->width !== null && $placement->height !== null) {
            return [$placement->width, $placement->height];
        }

        if ($placement->width !== null) {
            return [
                $placement->width,
                $placement->width * ($source->height / $source->width),
            ];
        }

        if ($placement->height !== null) {
            return [
                $placement->height * ($source->width / $source->height),
                $placement->height,
            ];
        }

        return [(float) $source->width, (float) $source->height];
    }

    private function textFlow(): TextFlow
    {
        return new TextFlow($this->buildCurrentPage(), $this->currentPageCursorY);
    }

    private function tableLayoutCalculator(): TableLayoutCalculator
    {
        return new TableLayoutCalculator();
    }

    private function textBlockBuilder(): TextBlockBuilder
    {
        return new TextBlockBuilder();
    }

    private function textShaper(): SimpleTextShaper
    {
        return new SimpleTextShaper();
    }

    private function fontRunMapper(): SimpleFontRunMapper
    {
        return new SimpleFontRunMapper();
    }

    /**
     * @param list<string> $lines
     * @return list<list<ShapedTextRun>>
     */
    private function shapeWrappedTextLines(
        array $lines,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): array {
        $shapedLines = [];

        foreach ($lines as $line) {
            $shapedLines[] = $line === ''
                ? []
                : $this->textShaper()->shape($line, $options->baseDirection, $font);
        }

        return $shapedLines;
    }

    /**
     * @param list<list<ShapedTextRun>> $shapedLines
     */
    private function containsShapedEmbeddedGlyphIds(array $shapedLines): bool
    {
        foreach ($shapedLines as $lineRuns) {
            foreach ($lineRuns as $run) {
                foreach ($run->glyphs as $glyph) {
                    if ($glyph->glyphId !== null) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param list<list<ShapedTextRun>> $shapedLines
     * @return list<EmbeddedGlyph>
     */
    private function embeddedGlyphsForShapedLines(array $shapedLines, EmbeddedFontDefinition $font): array
    {
        $embeddedGlyphs = [];

        foreach ($shapedLines as $lineRuns) {
            foreach ($lineRuns as $run) {
                foreach ($run->glyphs as $glyph) {
                    $unicodeCodePoint = $glyph->unicodeCodePoint ?? mb_ord($glyph->character, 'UTF-8');
                    $glyphId = $glyph->glyphId ?? $font->parser->getGlyphIdForCodePoint($unicodeCodePoint);
                    $key = $glyphId . ':' . $unicodeCodePoint;

                    if (isset($embeddedGlyphs[$key])) {
                        continue;
                    }

                    $embeddedGlyphs[$key] = new EmbeddedGlyph(
                        glyphId: $glyphId,
                        unicodeCodePoint: $unicodeCodePoint,
                        unicodeText: $glyph->unicodeText ?? $glyph->character,
                    );
                }
            }
        }

        return array_values($embeddedGlyphs);
    }

    /**
     * @param array<int, string> $differences
     */
    private function fontAliasFor(string $fontName, StandardFontEncoding $fontEncoding, array $differences = []): string
    {
        StandardFontDefinition::from($fontName);

        foreach ($this->currentPageFontResources as $alias => $pageFont) {
            if ($pageFont->matches($fontName, $fontEncoding, $differences)) {
                return $alias;
            }
        }

        $alias = 'F' . (count($this->currentPageFontResources) + 1);
        $this->currentPageFontResources[$alias] = new PageFont($fontName, $fontEncoding, $differences);

        return $alias;
    }

    private function embeddedFontAliasFor(EmbeddedFontDefinition $font): string
    {
        foreach ($this->currentPageFontResources as $alias => $pageFont) {
            if ($pageFont->matchesEmbedded($font)) {
                return $alias;
            }
        }

        $alias = 'F' . (count($this->currentPageFontResources) + 1);
        $this->currentPageFontResources[$alias] = PageFont::embedded($font);

        return $alias;
    }

    /**
     * @param list<EmbeddedGlyph> $embeddedGlyphs
     */
    private function embeddedUnicodeFontAliasFor(EmbeddedFontDefinition $font, array $embeddedGlyphs): string
    {
        foreach ($this->currentPageFontResources as $alias => $pageFont) {
            if (!$pageFont->matchesEmbedded($font, true)) {
                continue;
            }

            $this->currentPageFontResources[$alias] = $pageFont->withAdditionalEmbeddedGlyphs($embeddedGlyphs);

            return $alias;
        }

        $alias = 'F' . (count($this->currentPageFontResources) + 1);
        $this->currentPageFontResources[$alias] = PageFont::embeddedUnicode($font, $embeddedGlyphs);

        return $alias;
    }

    private function imageAliasFor(ImageSource $source): string
    {
        $sourceKey = $source->key();

        foreach ($this->currentPageImageResources as $alias => $imageSource) {
            if ($imageSource->key() === $sourceKey) {
                return $alias;
            }
        }

        $alias = 'Im' . (count($this->currentPageImageResources) + 1);
        $this->currentPageImageResources[$alias] = $source;

        return $alias;
    }

    private function colorFillOperator(Color $color): string
    {
        $components = implode(' ', array_map(
            fn (float $value): string => $this->formatNumber($value),
            $color->components(),
        ));

        return match ($color->space) {
            ColorSpace::GRAY => $components . ' g',
            ColorSpace::RGB => $components . ' rg',
            ColorSpace::CMYK => $components . ' k',
        };
    }

    private function colorStrokeOperator(Color $color): string
    {
        $components = implode(' ', array_map(
            fn (float $value): string => $this->formatNumber($value),
            $color->components(),
        ));

        return match ($color->space) {
            ColorSpace::GRAY => $components . ' G',
            ColorSpace::RGB => $components . ' RG',
            ColorSpace::CMYK => $components . ' K',
        };
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

}
