<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function file_exists;
use function file_get_contents;
use function implode;
use function is_readable;
use function mb_ord;
use function str_replace;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Debug\DebugSink;
use Kalle\Pdf\Debug\NullDebugSink;
use Kalle\Pdf\Debug\PsrDebugSink;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\RadioButtonChoice;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Document\Form\TextField;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsEntry;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TaggedPdf\TaggedFigure;
use Kalle\Pdf\Document\TaggedPdf\TaggedList;
use Kalle\Pdf\Document\TaggedPdf\TaggedListContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedListItem;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureElement;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureRoleRegistry;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableCell;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Document\TaggedPdf\TaggedTextBlock;
use Kalle\Pdf\Drawing\GraphicsAccessibility;
use Kalle\Pdf\Drawing\Path;
use Kalle\Pdf\Drawing\StrokeStyle;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFont;
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
use Kalle\Pdf\Page\AnnotationMetadata;
use Kalle\Pdf\Page\CaretAnnotation;
use Kalle\Pdf\Page\CaretAnnotationOptions;
use Kalle\Pdf\Page\CircleAnnotation;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\FileAttachmentAnnotation;
use Kalle\Pdf\Page\FileAttachmentAnnotationOptions;
use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\InkAnnotation;
use Kalle\Pdf\Page\InkAnnotationOptions;
use Kalle\Pdf\Page\LineAnnotation;
use Kalle\Pdf\Page\LineAnnotationOptions;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\MarkupAnnotationOptions;
use Kalle\Pdf\Page\NamedDestination;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PageAnnotationReference;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageImage;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\PolygonAnnotation;
use Kalle\Pdf\Page\PolygonAnnotationOptions;
use Kalle\Pdf\Page\PolyLineAnnotation;
use Kalle\Pdf\Page\PolyLineAnnotationOptions;
use Kalle\Pdf\Page\PopupAnnotationDefinition;
use Kalle\Pdf\Page\ShapeAnnotationOptions;
use Kalle\Pdf\Page\SquareAnnotation;
use Kalle\Pdf\Page\SquigglyAnnotation;
use Kalle\Pdf\Page\StampAnnotation;
use Kalle\Pdf\Page\StampAnnotationOptions;
use Kalle\Pdf\Page\StrikeOutAnnotation;
use Kalle\Pdf\Page\SupportsPopupAnnotation;
use Kalle\Pdf\Page\TextAnnotation;
use Kalle\Pdf\Page\TextAnnotationOptions;
use Kalle\Pdf\Page\UnderlineAnnotation;
use Kalle\Pdf\Text\DefaultScriptTextShaper;
use Kalle\Pdf\Text\MappedTextRun;
use Kalle\Pdf\Text\ShapedTextRun;
use Kalle\Pdf\Text\SimpleFontRunMapper;
use Kalle\Pdf\Text\SimpleTextShaper;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use Kalle\Pdf\Text\TextSemantic;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\StreamOutput;
use Kalle\Pdf\Writer\StringOutput;
use Psr\Log\LoggerInterface;
use Throwable;

class DefaultDocumentBuilder implements DocumentBuilder
{
    /** @var list<callable(PageDecorationContext, int): void> */
    private array $headerRenderers = [];
    /** @var list<callable(PageDecorationContext, int): void> */
    private array $footerRenderers = [];
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
    /** @var list<array{key: string, pageIndex: int, markedContentId: int, altText: ?string}> */
    private array $taggedFigures = [];
    /** @var list<array{key: string, tag: string, pageIndex: int, markedContentId: int}> */
    private array $taggedTextBlocks = [];
    /** @var array<int, list<array{label: array{pageIndex: int, markedContentId: int}, body: array{pageIndex: int, markedContentId: int}}>> */
    private array $taggedLists = [];
    /** @var array<string, array{tag: string, childKeys: list<string>}> */
    private array $taggedStructureElements = [];
    /** @var list<string> */
    private array $taggedDocumentChildKeys = [];
    /** @var list<string> */
    private array $taggedStructureStack = [];
    private int $nextTaggedTableId = 0;
    private int $nextTaggedListId = 0;
    private int $nextTaggedStructureElementId = 0;
    private ?Margin $currentPageMargin = null;
    private ?float $currentPageCursorY = null;
    private bool $currentPageCursorYIsTopBoundary = false;
    private ?Color $currentPageBackgroundColor = null;
    private ?string $currentPageLabel = null;
    private ?string $currentPageName = null;
    private ?string $title = null;
    private ?string $author = null;
    private ?string $subject = null;
    private ?string $keywords = null;
    private ?string $language = null;
    private ?string $creator = null;
    private ?string $creatorTool = null;
    private ?PdfAOutputIntent $pdfaOutputIntent = null;
    private ?Encryption $encryption = null;
    private ?Profile $profile = null;
    /** @var list<FileAttachment> */
    private array $attachments = [];
    /** @var list<Outline> */
    private array $outlines = [];
    /** @var list<TableOfContentsEntry> */
    private array $tableOfContentsEntries = [];
    private ?TableOfContentsOptions $tableOfContentsOptions = null;
    private ?AcroForm $acroForm = null;
    private ?DebugConfig $debugConfig = null;
    private ?DebugSink $debugSink = null;
    private bool $renderingPageDecoration = false;

    public static function make(): self
    {
        return new self();
    }

    public function debug(DebugConfig $config): self
    {
        $clone = clone $this;
        $clone->debugConfig = $config;

        return $clone;
    }

    public function withDebugSink(DebugSink $sink): self
    {
        $clone = clone $this;
        $clone->debugSink = $sink;

        return $clone;
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return $this->withDebugSink(new PsrDebugSink($logger));
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

    public function keywords(string $keywords): DocumentBuilder
    {
        $clone = clone $this;
        $clone->keywords = $keywords;

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

    public function header(callable $renderer): DocumentBuilder
    {
        $clone = clone $this;
        $clone->headerRenderers = [...$clone->headerRenderers, $renderer];

        return $clone;
    }

    public function headerOn(callable $predicate, callable $renderer): DocumentBuilder
    {
        return $this->header(
            static function (PageDecorationContext $page, int $pageNumber) use ($predicate, $renderer): void {
                if (!$predicate($page, $pageNumber)) {
                    return;
                }

                $renderer($page, $pageNumber);
            },
        );
    }

    public function footer(callable $renderer): DocumentBuilder
    {
        $clone = clone $this;
        $clone->footerRenderers = [...$clone->footerRenderers, $renderer];

        return $clone;
    }

    public function footerOn(callable $predicate, callable $renderer): DocumentBuilder
    {
        return $this->footer(
            static function (PageDecorationContext $page, int $pageNumber) use ($predicate, $renderer): void {
                if (!$predicate($page, $pageNumber)) {
                    return;
                }

                $renderer($page, $pageNumber);
            },
        );
    }

    public function pageNumbers(
        TextOptions $options,
        string $template = 'Page {{page}} / {{pages}}',
        bool $footer = true,
    ): DocumentBuilder {
        if ($template === '') {
            throw new InvalidArgumentException('Page number template must not be empty.');
        }

        $renderer = static function (PageDecorationContext $page) use ($options, $template): void {
            $page->text(
                str_replace(
                    ['{{page}}', '{{pages}}'],
                    [(string) $page->pageNumber(), (string) $page->totalPages()],
                    $template,
                ),
                $options,
            );
        };

        return $footer
            ? $this->footer($renderer)
            : $this->header($renderer);
    }

    public function content(string $content): DocumentBuilder
    {
        $clone = clone $this;
        $clone->currentPageContents = $content;

        return $clone;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function text(string | array $text, ?TextOptions $options = null): DocumentBuilder
    {
        if (is_array($text)) {
            return $this->renderTextSegments($text, $options, $this->resolveTaggedTextTag($options));
        }

        return $this->renderTextBlock($text, $options, $this->resolveTaggedTextTag($options));
    }

    /**
     * @param list<string|TextSegment> $lines
     */
    public function textLines(array $lines, ?TextOptions $options = null): DocumentBuilder
    {
        return $this->renderTextLines($lines, $options, $this->resolveTaggedTextTag($options));
    }

    public function beginStructure(TaggedStructureTag $tag): DocumentBuilder
    {
        $tag = $tag->value;

        $registry = new TaggedStructureRoleRegistry();
        $registry->assertKnownTag($tag);

        if (!$registry->isContainerTag($tag)) {
            throw new InvalidArgumentException(sprintf(
                'Tagged structure tag "%s" is not supported as a container. Use TextOptions(tag: ...) for leaf roles.',
                $tag,
            ));
        }

        $clone = clone $this;
        $key = 'struct:' . $clone->nextTaggedStructureElementId++;
        $clone->taggedStructureElements[$key] = [
            'tag' => $tag,
            'childKeys' => [],
        ];
        $clone->attachTaggedStructureChildKey($key);
        $clone->taggedStructureStack[] = $key;

        return $clone;
    }

    public function endStructure(): DocumentBuilder
    {
        if ($this->taggedStructureStack === []) {
            throw new InvalidArgumentException('No tagged structure is currently open.');
        }

        $clone = clone $this;
        array_pop($clone->taggedStructureStack);

        return $clone;
    }

    /**
     * @param list<string> $items
     */
    public function list(array $items, ?ListOptions $list = null, ?TextOptions $text = null): DocumentBuilder
    {
        $clone = clone $this;
        $list ??= new ListOptions();
        $text ??= TextOptions::make();

        if ($items === []) {
            return $clone;
        }

        $font = $text->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($text->embeddedFont)
            : StandardFontDefinition::from($text->fontName);
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($text, $font);
        $baseX = $placement['x'];
        $currentY = $placement['y'];
        $lineHeight = $textFlow->lineHeight($text);
        $indent = max($text->fontSize * 1.5, 18.0);
        $markerGap = max($text->fontSize * 0.5, 6.0);
        $markerWidth = max($indent - $markerGap, 0.0);
        $taggedListId = $clone->requiresTaggedStructure() ? $clone->nextTaggedListId++ : null;

        foreach ($items as $index => $item) {
            if ($item === '') {
                throw new InvalidArgumentException(sprintf(
                    'List item %d must not be empty.',
                    $index + 1,
                ));
            }

            $labelMarkedContentId = $taggedListId !== null ? $clone->nextTaggedMarkedContentId() : null;
            $bodyMarkedContentId = $taggedListId !== null ? $clone->nextTaggedMarkedContentId() : null;
            $labelOptions = $this->copyTextOptions(
                $text,
                x: $baseX,
                y: $currentY,
                width: $markerWidth > 0.0 ? $markerWidth : null,
                spacingBefore: null,
                spacingAfter: null,
            );
            $bodyWidth = $text->width !== null
                ? max($text->width - $indent, 0.0)
                : null;
            $bodyOptions = $this->copyTextOptions(
                $text,
                x: $baseX + $indent,
                y: $currentY,
                width: $bodyWidth,
                spacingBefore: null,
                spacingAfter: null,
            );
            $labelResult = $clone->renderTextBlockAt(
                $this->listItemLabel($list, $index),
                $labelOptions,
                $font,
                $textFlow,
                $baseX,
                $currentY,
                'Lbl',
                $labelMarkedContentId,
            );
            $bodyResult = $clone->renderTextBlockAt(
                $item,
                $bodyOptions,
                $font,
                $textFlow,
                $baseX + $indent,
                $currentY,
                'LBody',
                $bodyMarkedContentId,
            );

            $clone->currentPageContents = $this->appendPageContent(
                $clone->currentPageContents,
                $this->appendPageContent($labelResult['contents'], $bodyResult['contents']),
            );
            $clone->currentPageAnnotations = [
                ...$clone->currentPageAnnotations,
                ...$labelResult['annotations'],
                ...$bodyResult['annotations'],
            ];

            if ($taggedListId !== null && $labelMarkedContentId !== null && $bodyMarkedContentId !== null) {
                $clone->registerTaggedListItem($taggedListId, $labelMarkedContentId, $bodyMarkedContentId);
            }

            $currentY -= $lineHeight * max($bodyResult['lineCount'], 1);
        }

        $clone->currentPageCursorY = $currentY - ($text->spacingAfter ?? 0.0);
        $clone->currentPageCursorYIsTopBoundary = false;

        return $clone;
    }

    /**
     * @param list<TextSegment> $segments
     */
    private function renderTextSegments(array $segments, ?TextOptions $options, ?string $markedContentTag): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= TextOptions::make();
        $artifact = $options->semantic === TextSemantic::ARTIFACT;

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
        $markedContentId = $markedContentTag !== null ? $clone->nextTaggedMarkedContentId() : null;
        $textResult = $clone->buildWrappedTextSegmentsContent(
            $wrappedSegmentLines,
            $options,
            $textFlow,
            $placement['x'],
            $placement['y'],
            ($this->profile ?? Profile::standard())->version(),
            $markedContentTag,
            $markedContentId,
            $artifact,
        );

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $textResult['contents'],
        );
        $clone->currentPageAnnotations = [...$clone->currentPageAnnotations, ...$textResult['annotations']];
        $clone->currentPageCursorY = $textFlow->nextCursorY($options, $placement['y'], count($wrappedSegmentLines));
        $clone->currentPageCursorYIsTopBoundary = false;
        if ($markedContentTag !== null && $markedContentId !== null && $textResult['contents'] !== '') {
            $clone->registerTaggedTextBlock($markedContentTag, $markedContentId);
        }

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
        $columnWidths = $calculator->resolveColumnWidths($table, $tableWidth, new TextFlow($page), $font);
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
        $taggedTableId = $clone->requiresTaggedStructure()
            ? $clone->registerTaggedTable($headerLayout, $tableLayout, $footerLayout)
            : null;
        $explicitStartY = $table->placement?->y;

        if ($explicitStartY !== null && $clone->currentPageCursorY !== null && $explicitStartY > $clone->currentPageCursorY) {
            throw new DocumentValidationException(
                DocumentBuildError::TABLE_LAYOUT_INVALID,
                'Explicit table placement y must not be above the current flow cursor on the page.',
            );
        }

        $cursorY = $explicitStartY ?? $clone->currentPageCursorY ?? $contentArea->top;
        $headerRenderedOnCurrentPage = false;
        $bodyRenderedOnCurrentPage = false;
        $minimumTableSegmentHeight = $table->cellPadding->vertical() + $clone->lineHeightForTable($table);
        $minimumTableStartHeight = $minimumTableSegmentHeight + ($headerLayout?->totalHeight() ?? 0.0);
        $repeatedFooterHeight = $footerLayout !== null && $table->repeatFooterOnPageBreak
            ? $footerLayout->totalHeight()
            : 0.0;

        if ($captionLayout !== null) {
            if (($captionLayout['height'] + $minimumTableStartHeight) > $contentArea->height()) {
                throw new DocumentValidationException(
                    DocumentBuildError::TABLE_LAYOUT_INVALID,
                    'Table caption leaves no space for table content on a fresh page.',
                );
            }

            if (($captionLayout['height'] + $minimumTableStartHeight) > ($cursorY - $contentArea->bottom) && $explicitStartY !== null) {
                throw new DocumentValidationException(
                    DocumentBuildError::TABLE_LAYOUT_INVALID,
                    'Explicit table placement y leaves no space for caption and table start.',
                );
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
            $clone->currentPageCursorYIsTopBoundary = false;
        }

        if ($headerLayout !== null) {
            if ($headerLayout->totalHeight() > ($cursorY - $contentArea->bottom) && $explicitStartY !== null) {
                throw new DocumentValidationException(
                    DocumentBuildError::TABLE_LAYOUT_INVALID,
                    'Explicit table placement y leaves no space for the configured header rows.',
                );
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
            $clone->currentPageCursorYIsTopBoundary = false;
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
                $availableHeightAfterHeader = $availableHeight - $headerHeight - $repeatedFooterHeight;
                $groupFitsAfterHeader = $remainingGroupHeight <= $availableHeightAfterHeader;
                $groupFitsOnFreshPage = $remainingGroupHeight <= ($contentArea->height() - $headerHeight - $repeatedFooterHeight);

                if (($contentArea->height() - $headerHeight - $repeatedFooterHeight) < $minimumTableSegmentHeight) {
                    throw new DocumentValidationException(
                        DocumentBuildError::TABLE_LAYOUT_INVALID,
                        'Page content area is too small to render table rows.',
                    );
                }

                if (!$groupFitsAfterHeader && $clone->currentPageCursorY !== null && $groupFitsOnFreshPage) {
                    if ($bodyRenderedOnCurrentPage && $footerLayout !== null && $table->repeatFooterOnPageBreak) {
                        $clone->renderTableLayout($table, $footerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'footer');
                        $cursorY -= $footerLayout->totalHeight();
                        $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
                        $clone->currentPageCursorYIsTopBoundary = false;
                    }
                    $clone->startOverflowPage();
                    $page = $clone->buildCurrentPage();
                    $contentArea = $page->contentArea();
                    ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                    $cursorY = $contentArea->top;
                    $headerRenderedOnCurrentPage = false;
                    $bodyRenderedOnCurrentPage = false;
                    continue;
                }

                if (!$headerRenderedOnCurrentPage && $headerLayout !== null && $table->repeatHeaderOnPageBreak) {
                    if ($availableHeightAfterHeader < $minimumTableSegmentHeight) {
                        throw new DocumentValidationException(
                            DocumentBuildError::TABLE_LAYOUT_INVALID,
                            'Repeated table headers leave no space for table content on the page.',
                        );
                    }

                    $clone->renderTableLayout($table, $headerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'header');
                    $cursorY -= $headerLayout->totalHeight();
                    $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
                    $clone->currentPageCursorYIsTopBoundary = false;
                    $headerRenderedOnCurrentPage = true;
                    $availableHeight = $cursorY - $contentArea->bottom - $repeatedFooterHeight;
                }

                if ($availableHeight < $minimumTableSegmentHeight) {
                    if ($bodyRenderedOnCurrentPage && $footerLayout !== null && $table->repeatFooterOnPageBreak) {
                        $clone->renderTableLayout($table, $footerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'footer');
                        $cursorY -= $footerLayout->totalHeight();
                        $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
                        $clone->currentPageCursorYIsTopBoundary = false;
                    }
                    $clone->startOverflowPage();
                    $page = $clone->buildCurrentPage();
                    $contentArea = $page->contentArea();
                    ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                    $cursorY = $contentArea->top;
                    $headerRenderedOnCurrentPage = false;
                    $bodyRenderedOnCurrentPage = false;
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
                $bodyRenderedOnCurrentPage = true;
                $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
                $clone->currentPageCursorYIsTopBoundary = false;

                if ($segmentOffset < $rowGroup->height) {
                    if ($footerLayout !== null && $table->repeatFooterOnPageBreak) {
                        $clone->renderTableLayout($table, $footerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'footer');
                        $cursorY -= $footerLayout->totalHeight();
                        $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
                        $clone->currentPageCursorYIsTopBoundary = false;
                    }
                    $clone->startOverflowPage();
                    $page = $clone->buildCurrentPage();
                    $contentArea = $page->contentArea();
                    ['x' => $tableLeftX, 'width' => $tableWidth] = $clone->resolveTablePlacement($table, $page);
                    $cursorY = $contentArea->top;
                    $headerRenderedOnCurrentPage = false;
                    $bodyRenderedOnCurrentPage = false;
                }
            }
        }

        if ($footerLayout !== null) {
            $headerHeight = !$headerRenderedOnCurrentPage && $headerLayout !== null && $table->repeatHeaderOnPageBreak
                ? $headerLayout->totalHeight()
                : 0.0;
            $requiredHeight = $footerLayout->totalHeight() + $headerHeight;

            if ($requiredHeight > $contentArea->height()) {
                throw new DocumentValidationException(
                    DocumentBuildError::TABLE_LAYOUT_INVALID,
                    'Table footer rows must fit on a fresh page.',
                );
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
                $clone->currentPageCursorYIsTopBoundary = false;
                $headerRenderedOnCurrentPage = true;
            }

            $clone->renderTableLayout($table, $footerLayout, $font, $cursorY, $tableLeftX, $taggedTableId, 'footer');
            $cursorY -= $footerLayout->totalHeight();
            $clone->currentPageCursorY = $clone->nextTableCursorY($table, $page, $cursorY);
            $clone->currentPageCursorYIsTopBoundary = false;
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
        $structureKey = $markedContentId !== null
            ? 'figure:image:' . count($clone->pages) . ':' . count($clone->currentPageImages)
            : null;
        $clone->currentPageImages[] = new PageImage(
            $imageAlias,
            $placement,
            $accessibility,
            $markedContentId,
            $structureKey,
        );

        if ($structureKey !== null) {
            $clone->attachTaggedStructureChildKey($structureKey);
        }

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

    public function line(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        ?StrokeStyle $stroke = null,
        ?GraphicsAccessibility $accessibility = null,
    ): DocumentBuilder {
        $clone = clone $this;
        $stroke ??= new StrokeStyle();
        $markedContentId = $clone->markedContentIdForGraphic($accessibility);
        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->buildGraphicsContent(implode("\n", [
                'q',
                ...$this->buildStrokeStyleOperators($stroke),
                $this->formatNumber($x1) . ' ' . $this->formatNumber($y1) . ' m',
                $this->formatNumber($x2) . ' ' . $this->formatNumber($y2) . ' l',
                'S',
                'Q',
            ]), $accessibility, $markedContentId),
        );

        if ($markedContentId !== null) {
            $clone->registerTaggedFigure($markedContentId, $accessibility?->altText);
        }

        $clone->advanceCursorToGraphicTop(min($y1, $y2));

        return $clone;
    }

    public function rectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        ?StrokeStyle $stroke = null,
        ?Color $fillColor = null,
        ?GraphicsAccessibility $accessibility = null,
    ): DocumentBuilder {
        if ($width <= 0.0) {
            throw new InvalidArgumentException('Rectangle width must be greater than zero.');
        }

        if ($height <= 0.0) {
            throw new InvalidArgumentException('Rectangle height must be greater than zero.');
        }

        $stroke ??= $fillColor === null ? new StrokeStyle() : null;

        if ($stroke === null && $fillColor === null) {
            throw new InvalidArgumentException('Rectangle requires either a stroke or a fill.');
        }

        $clone = clone $this;
        $markedContentId = $clone->markedContentIdForGraphic($accessibility);
        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->buildGraphicsContent(
                $this->buildRectangleContent($x, $y, $width, $height, $stroke, $fillColor),
                $accessibility,
                $markedContentId,
            ),
        );

        if ($markedContentId !== null) {
            $clone->registerTaggedFigure($markedContentId, $accessibility?->altText);
        }

        return $clone;
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
    ): DocumentBuilder {
        return $this->path(
            Path::roundedRectangle($x, $y, $width, $height, $radius),
            $stroke ?? ($fillColor === null ? new StrokeStyle() : null),
            $fillColor,
            $accessibility,
        );
    }

    public function path(
        Path $path,
        ?StrokeStyle $stroke = null,
        ?Color $fillColor = null,
        ?GraphicsAccessibility $accessibility = null,
    ): DocumentBuilder {
        $stroke ??= $fillColor === null ? new StrokeStyle() : null;

        if ($stroke === null && $fillColor === null) {
            throw new InvalidArgumentException('Path requires either a stroke or a fill.');
        }

        $clone = clone $this;
        $markedContentId = $clone->markedContentIdForGraphic($accessibility);
        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->buildGraphicsContent(
                $this->buildPathContent($path, $stroke, $fillColor),
                $accessibility,
                $markedContentId,
            ),
        );

        if ($markedContentId !== null) {
            $clone->registerTaggedFigure($markedContentId, $accessibility?->altText);
        }

        return $clone;
    }

    public function attachment(
        string $filename,
        string $contents,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $associatedFileRelationship = null,
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->attachments[] = new FileAttachment(
            $filename,
            new EmbeddedFile($contents, $mimeType),
            $description,
            $associatedFileRelationship,
        );

        return $clone;
    }

    public function attachmentFromFile(
        string $path,
        ?string $filename = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $associatedFileRelationship = null,
    ): DocumentBuilder {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf(
                "Attachment file '%s' does not exist.",
                $path,
            ));
        }

        if (!is_readable($path)) {
            throw new InvalidArgumentException(sprintf(
                "Attachment file '%s' could not be read.",
                $path,
            ));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException(sprintf(
                "Attachment file '%s' could not be read.",
                $path,
            ));
        }

        return $this->attachment(
            $filename ?? basename($path),
            $contents,
            $description,
            $mimeType,
            $associatedFileRelationship,
        );
    }

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
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->acroForm = ($clone->acroForm ?? new AcroForm())->withField(
            new TextField(
                name: $name,
                pageNumber: count($clone->pages) + 1,
                x: $x,
                y: $y,
                width: $width,
                height: $height,
                value: $value,
                alternativeName: $alternativeName,
                defaultValue: $defaultValue,
                fontSize: $fontSize,
                multiline: $multiline,
            ),
        );

        return $clone;
    }

    public function checkbox(
        string $name,
        float $x,
        float $y,
        float $size,
        bool $checked = false,
        ?string $alternativeName = null,
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->acroForm = ($clone->acroForm ?? new AcroForm())->withField(
            new CheckboxField(
                name: $name,
                pageNumber: count($clone->pages) + 1,
                x: $x,
                y: $y,
                size: $size,
                checked: $checked,
                alternativeName: $alternativeName,
            ),
        );

        return $clone;
    }

    public function radioButton(
        string $groupName,
        string $exportValue,
        float $x,
        float $y,
        float $size,
        bool $checked = false,
        ?string $alternativeName = null,
        ?string $groupAlternativeName = null,
    ): DocumentBuilder {
        $clone = clone $this;
        $acroForm = $clone->acroForm ?? new AcroForm();
        $existingField = $acroForm->field($groupName);

        if ($existingField !== null && !$existingField instanceof RadioButtonGroup) {
            throw new InvalidArgumentException(sprintf(
                'AcroForm field "%s" is already registered with a different field type.',
                $groupName,
            ));
        }

        $group = $existingField ?? new RadioButtonGroup($groupName, alternativeName: $groupAlternativeName);

        if ($groupAlternativeName !== null && $existingField instanceof RadioButtonGroup) {
            $group = new RadioButtonGroup($group->name, $group->choices, $groupAlternativeName);
        }

        $group = $group->withChoice(new RadioButtonChoice(
            pageNumber: count($clone->pages) + 1,
            x: $x,
            y: $y,
            size: $size,
            exportValue: $exportValue,
            checked: $checked,
            alternativeName: $alternativeName,
        ));

        $clone->acroForm = $acroForm->replacingField($group);

        return $clone;
    }

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
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->acroForm = ($clone->acroForm ?? new AcroForm())->withField(
            new ComboBoxField(
                name: $name,
                pageNumber: count($clone->pages) + 1,
                x: $x,
                y: $y,
                width: $width,
                height: $height,
                options: $options,
                value: $value,
                alternativeName: $alternativeName,
                defaultValue: $defaultValue,
                fontSize: $fontSize,
            ),
        );

        return $clone;
    }

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
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->acroForm = ($clone->acroForm ?? new AcroForm())->withField(
            new ListBoxField(
                name: $name,
                pageNumber: count($clone->pages) + 1,
                x: $x,
                y: $y,
                width: $width,
                height: $height,
                options: $options,
                value: $value,
                alternativeName: $alternativeName,
                defaultValue: $defaultValue,
                fontSize: $fontSize,
            ),
        );

        return $clone;
    }

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
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->acroForm = ($clone->acroForm ?? new AcroForm())->withField(
            new PushButtonField(
                name: $name,
                pageNumber: count($clone->pages) + 1,
                x: $x,
                y: $y,
                width: $width,
                height: $height,
                label: $label,
                alternativeName: $alternativeName,
                url: $url,
                fontSize: $fontSize,
            ),
        );

        return $clone;
    }

    public function signatureField(
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $alternativeName = null,
    ): DocumentBuilder {
        $clone = clone $this;
        $clone->acroForm = ($clone->acroForm ?? new AcroForm())->withField(
            new SignatureField(
                name: $name,
                pageNumber: count($clone->pages) + 1,
                x: $x,
                y: $y,
                width: $width,
                height: $height,
                alternativeName: $alternativeName,
            ),
        );

        return $clone;
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

    public function underlineAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->underlineAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            new MarkupAnnotationOptions(
                color: $color,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function underlineAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        MarkupAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new UnderlineAnnotation(
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

    public function strikeOutAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->strikeOutAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            new MarkupAnnotationOptions(
                color: $color,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function strikeOutAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        MarkupAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new StrikeOutAnnotation(
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

    public function squigglyAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->squigglyAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            new MarkupAnnotationOptions(
                color: $color,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function squigglyAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        MarkupAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new SquigglyAnnotation(
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
                metadata: new AnnotationMetadata(title: $title),
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
        $textOptions ??= TextOptions::make(fontSize: 12.0);
        $options ??= new FreeTextAnnotationOptions();
        $metadata = $options->metadata();
        $resolvedTextColor = $options->textColor ?? $textOptions->color;
        $font = $textOptions->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($textOptions->embeddedFont)
            : StandardFontDefinition::from($textOptions->fontName);
        $appearanceOptions = TextOptions::make(
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

    public function stampAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        string $icon = 'Draft',
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->stampAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            new StampAnnotationOptions(
                icon: $icon,
                color: $color,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function stampAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        StampAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new StampAnnotation(
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            icon: $options->icon,
            color: $options->color,
            contents: $metadata->contents,
            title: $metadata->title,
        );

        return $clone;
    }

    public function squareAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->squareAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            new ShapeAnnotationOptions(
                borderColor: $borderColor,
                fillColor: $fillColor,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function squareAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        ShapeAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new SquareAnnotation(
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            borderColor: $options->borderColor,
            fillColor: $options->fillColor,
            contents: $metadata->contents,
            title: $metadata->title,
            borderStyle: $options->borderStyle,
        );

        return $clone;
    }

    public function circleAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->circleAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            new ShapeAnnotationOptions(
                borderColor: $borderColor,
                fillColor: $fillColor,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function circleAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        ShapeAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new CircleAnnotation(
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            borderColor: $options->borderColor,
            fillColor: $options->fillColor,
            contents: $metadata->contents,
            title: $metadata->title,
            borderStyle: $options->borderStyle,
        );

        return $clone;
    }

    public function caretAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $contents = null,
        ?string $title = null,
        string $symbol = 'None',
    ): self {
        return $this->caretAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            new CaretAnnotationOptions(
                contents: $contents,
                title: $title,
                symbol: $symbol,
            ),
        );
    }

    public function caretAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        CaretAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new CaretAnnotation(
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            contents: $metadata->contents,
            title: $metadata->title,
            symbol: $options->symbol,
        );

        return $clone;
    }

    public function inkAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        array $paths,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->inkAnnotationWithOptions(
            $x,
            $y,
            $width,
            $height,
            $paths,
            new InkAnnotationOptions(
                color: $color,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function inkAnnotationWithOptions(
        float $x,
        float $y,
        float $width,
        float $height,
        array $paths,
        InkAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new InkAnnotation(
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            paths: $paths,
            color: $options->color,
            contents: $metadata->contents,
            title: $metadata->title,
        );

        return $clone;
    }

    public function lineAnnotation(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->lineAnnotationWithOptions(
            $x1,
            $y1,
            $x2,
            $y2,
            new LineAnnotationOptions(
                color: $color,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function lineAnnotationWithOptions(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        LineAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new LineAnnotation(
            x1: $x1,
            y1: $y1,
            x2: $x2,
            y2: $y2,
            color: $options->color,
            contents: $metadata->contents,
            title: $metadata->title,
            startStyle: $options->startStyle,
            endStyle: $options->endStyle,
            subject: $metadata->subject,
            borderStyle: $options->borderStyle,
        );

        return $clone;
    }

    public function polyLineAnnotation(
        array $vertices,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->polyLineAnnotationWithOptions(
            $vertices,
            new PolyLineAnnotationOptions(
                color: $color,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function polyLineAnnotationWithOptions(
        array $vertices,
        PolyLineAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new PolyLineAnnotation(
            vertices: $vertices,
            color: $options->color,
            contents: $metadata->contents,
            title: $metadata->title,
            startStyle: $options->startStyle,
            endStyle: $options->endStyle,
            subject: $metadata->subject,
            borderStyle: $options->borderStyle,
        );

        return $clone;
    }

    public function polygonAnnotation(
        array $vertices,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->polygonAnnotationWithOptions(
            $vertices,
            new PolygonAnnotationOptions(
                borderColor: $borderColor,
                fillColor: $fillColor,
                contents: $contents,
                title: $title,
            ),
        );
    }

    public function polygonAnnotationWithOptions(
        array $vertices,
        PolygonAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new PolygonAnnotation(
            vertices: $vertices,
            borderColor: $options->borderColor,
            fillColor: $options->fillColor,
            contents: $metadata->contents,
            title: $metadata->title,
            subject: $metadata->subject,
            borderStyle: $options->borderStyle,
        );

        return $clone;
    }

    public function popupAnnotation(
        float $x,
        float $y,
        float $width,
        float $height,
        bool $open = false,
    ): self {
        return $this->popupAnnotationWithDefinition(
            new PopupAnnotationDefinition(
                x: $x,
                y: $y,
                width: $width,
                height: $height,
                open: $open,
            ),
        );
    }

    public function popupAnnotationWithDefinition(PopupAnnotationDefinition $definition): self
    {
        return $this->popupAnnotationForWithDefinition($this->lastPageAnnotationReference(), $definition);
    }

    public function popupAnnotationFor(
        PageAnnotationReference $reference,
        float $x,
        float $y,
        float $width,
        float $height,
        bool $open = false,
    ): self {
        return $this->popupAnnotationForWithDefinition(
            $reference,
            new PopupAnnotationDefinition(
                x: $x,
                y: $y,
                width: $width,
                height: $height,
                open: $open,
            ),
        );
    }

    public function popupAnnotationForWithDefinition(PageAnnotationReference $reference, PopupAnnotationDefinition $definition): self
    {
        $clone = clone $this;
        $annotationIndex = $clone->resolveCurrentPageAnnotationReference($reference);

        $annotation = $clone->currentPageAnnotations[$annotationIndex];

        if (!$annotation instanceof SupportsPopupAnnotation) {
            throw new InvalidArgumentException('The referenced page annotation does not support popup annotations.');
        }

        $updatedAnnotation = $annotation->withPopup($definition);

        if (!$updatedAnnotation instanceof PageAnnotation) {
            throw new InvalidArgumentException('Popup annotation support must return a page annotation instance.');
        }

        $updatedAnnotations = $clone->currentPageAnnotations;
        $updatedAnnotations[$annotationIndex] = $updatedAnnotation;
        /** @var list<PageAnnotation> $normalizedAnnotations */
        $normalizedAnnotations = array_values($updatedAnnotations);
        $clone->currentPageAnnotations = $normalizedAnnotations;

        return $clone;
    }

    public function lastPageAnnotationReference(): PageAnnotationReference
    {
        $annotationIndex = array_key_last($this->currentPageAnnotations);

        if ($annotationIndex === null) {
            throw new InvalidArgumentException('No page annotation is available on the current page.');
        }

        return new PageAnnotationReference(
            pageNumber: count($this->pages) + 1,
            annotationIndex: $annotationIndex,
        );
    }

    public function fileAttachmentAnnotation(
        string $filename,
        EmbeddedFile $embeddedFile,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $description = null,
        string $icon = 'PushPin',
        ?string $contents = null,
        ?AssociatedFileRelationship $associatedFileRelationship = null,
    ): self {
        return $this->fileAttachmentAnnotationWithOptions(
            $filename,
            $embeddedFile,
            $x,
            $y,
            $width,
            $height,
            new FileAttachmentAnnotationOptions(
                description: $description,
                associatedFileRelationship: $associatedFileRelationship,
                icon: $icon,
                contents: $contents,
            ),
        );
    }

    public function fileAttachmentAnnotationWithOptions(
        string $filename,
        EmbeddedFile $embeddedFile,
        float $x,
        float $y,
        float $width,
        float $height,
        FileAttachmentAnnotationOptions $options,
    ): self {
        $clone = clone $this;
        $profile = $clone->profileOrDefault();

        if ($profile->isPdfA4()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow page-level file attachment annotations in the %s. Use document-level associated files instead.',
                $profile->name(),
                $profile->pdfaConformance() === 'E'
                    ? 'current constrained PDF/A-4e scope'
                    : 'current PDF/A-4 scope',
            ));
        }

        if (!$profile->supportsEmbeddedFileAttachments()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow embedded file attachments.',
                $profile->name(),
            ));
        }

        $attachment = $clone->reuseOrAppendAttachment(new FileAttachment(
            filename: $filename,
            embeddedFile: $embeddedFile,
            description: $options->description,
            associatedFileRelationship: $options->associatedFileRelationship,
        ));
        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new FileAttachmentAnnotation(
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            attachmentFilename: $attachment->filename,
            icon: $options->icon,
            contents: $metadata->contents,
        );

        return $clone;
    }

    public function existingFileAttachmentAnnotation(
        string $filename,
        float $x,
        float $y,
        float $width,
        float $height,
        string $icon = 'PushPin',
        ?string $contents = null,
    ): self {
        return $this->existingFileAttachmentAnnotationWithOptions(
            $filename,
            $x,
            $y,
            $width,
            $height,
            new FileAttachmentAnnotationOptions(
                icon: $icon,
                contents: $contents,
            ),
        );
    }

    public function existingFileAttachmentAnnotationWithOptions(
        string $filename,
        float $x,
        float $y,
        float $width,
        float $height,
        FileAttachmentAnnotationOptions $options,
    ): self {
        $clone = clone $this;

        if (!$clone->profileOrDefault()->supportsEmbeddedFileAttachments()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow embedded file attachments.',
                $clone->profileOrDefault()->name(),
            ));
        }

        $attachment = $clone->findAttachmentByFilename($filename);

        if ($attachment === null) {
            throw new InvalidArgumentException(sprintf(
                'Attachment "%s" does not exist in the document.',
                $filename,
            ));
        }

        $metadata = $options->metadata();
        $clone->currentPageAnnotations[] = new FileAttachmentAnnotation(
            x: $x,
            y: $y,
            width: $width,
            height: $height,
            attachmentFilename: $attachment->filename,
            icon: $options->icon,
            contents: $metadata->contents,
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

    public function addOutline(Outline $outline): DocumentBuilder
    {
        $clone = clone $this;
        $clone->outlines[] = $outline;

        return $clone;
    }

    public function outline(string $title): DocumentBuilder
    {
        return $this->outlineLevel($title, 1);
    }

    public function outlineAt(string $title, int $pageNumber, ?float $x = null, ?float $y = null): DocumentBuilder
    {
        return $this->outlineAtLevel($title, 1, $pageNumber, $x, $y);
    }

    public function outlineLevel(string $title, int $level): DocumentBuilder
    {
        return $this->appendOutline($title, $level, count($this->pages) + 1, true);
    }

    public function outlineAtLevel(string $title, int $level, int $pageNumber, ?float $x = null, ?float $y = null): DocumentBuilder
    {
        return $this->appendOutline($title, $level, $pageNumber, true, $x, $y);
    }

    public function outlineClosed(string $title): DocumentBuilder
    {
        return $this->outlineLevelClosed($title, 1);
    }

    public function outlineAtClosed(string $title, int $pageNumber, ?float $x = null, ?float $y = null): DocumentBuilder
    {
        return $this->outlineAtLevelClosed($title, 1, $pageNumber, $x, $y);
    }

    public function outlineLevelClosed(string $title, int $level): DocumentBuilder
    {
        return $this->appendOutline($title, $level, count($this->pages) + 1, false);
    }

    public function outlineAtLevelClosed(string $title, int $level, int $pageNumber, ?float $x = null, ?float $y = null): DocumentBuilder
    {
        return $this->appendOutline($title, $level, $pageNumber, false, $x, $y);
    }

    public function outlineChild(string $title): DocumentBuilder
    {
        return $this->appendRelativeOutline($title, 1, true);
    }

    public function outlineChildClosed(string $title): DocumentBuilder
    {
        return $this->appendRelativeOutline($title, 1, false);
    }

    public function outlineSibling(string $title): DocumentBuilder
    {
        return $this->appendRelativeOutline($title, 0, true);
    }

    public function outlineSiblingClosed(string $title): DocumentBuilder
    {
        return $this->appendRelativeOutline($title, 0, false);
    }

    private function appendOutline(
        string $title,
        int $level,
        int $pageNumber,
        bool $open,
        ?float $x = null,
        ?float $y = null,
    ): DocumentBuilder {
        if (($x === null) !== ($y === null)) {
            throw new InvalidArgumentException('Outline coordinates must be provided together.');
        }

        if ($x === null || $y === null) {
            return $this->addOutline(Outline::page($title, $pageNumber, $level, $open));
        }

        return $this->addOutline(Outline::position($title, $pageNumber, $x, $y, $level, $open));
    }

    private function appendRelativeOutline(string $title, int $levelOffset, bool $open): DocumentBuilder
    {
        if ($this->outlines === []) {
            throw new InvalidArgumentException('Relative outline helpers require an existing outline.');
        }

        $previousOutline = $this->outlines[count($this->outlines) - 1];

        return $this->appendOutline(
            $title,
            $previousOutline->level + $levelOffset,
            count($this->pages) + 1,
            $open,
        );
    }

    public function tableOfContents(?TableOfContentsOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $clone->tableOfContentsOptions = $options ?? new TableOfContentsOptions();

        return $clone;
    }

    public function tableOfContentsEntry(string $title): DocumentBuilder
    {
        $clone = clone $this;
        $clone->tableOfContentsEntries[] = TableOfContentsEntry::page($title, count($this->pages) + 1);

        return $clone;
    }

    public function tableOfContentsEntryAt(string $title, int $pageNumber, ?float $x = null, ?float $y = null): DocumentBuilder
    {
        if (($x === null) !== ($y === null)) {
            throw new InvalidArgumentException('Table of contents entry coordinates must be provided together.');
        }

        $clone = clone $this;
        $clone->tableOfContentsEntries[] = ($x === null || $y === null)
            ? TableOfContentsEntry::page($title, $pageNumber)
            : TableOfContentsEntry::position($title, $pageNumber, $x, $y);

        return $clone;
    }

    public function glyphs(StandardFontGlyphRun $glyphRun, ?TextOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= TextOptions::make(fontName: $glyphRun->fontName);

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
        $clone->currentPageCursorYIsTopBoundary = false;

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
        if ($this->taggedStructureStack !== []) {
            throw new DocumentValidationException(
                DocumentBuildError::TAGGED_STRUCTURE_UNCLOSED,
                'Cannot build document with unclosed tagged structures.',
            );
        }

        $pages = $this->applyPageDecorators([...$this->pages, $this->buildCurrentPage()]);
        $debugger = $this->buildDebugger();
        $document = new Document(
            profile: $this->profile ?? Profile::standard(),
            pages: $pages,
            title: $this->title,
            author: $this->author,
            subject: $this->subject,
            keywords: $this->keywords,
            language: $this->language,
            creator: $this->creator,
            creatorTool: $this->creatorTool,
            pdfaOutputIntent: $this->pdfaOutputIntent,
            encryption: $this->encryption,
            taggedFigures: $this->buildTaggedFigures(),
            taggedTables: $this->buildTaggedTables(),
            taggedTextBlocks: $this->buildTaggedTextBlocks(),
            attachments: $this->attachments,
            outlines: $this->outlines,
            acroForm: $this->acroForm,
            taggedLists: $this->buildTaggedLists(),
            taggedStructureElements: $this->buildTaggedStructureElements(),
            taggedDocumentChildKeys: $this->taggedDocumentChildKeys,
            debugger: $debugger,
        );

        if ($this->tableOfContentsOptions !== null) {
            $document = new DocumentTableOfContentsBuilder()->build(
                $document,
                $this->tableOfContentsOptions,
                $this->tableOfContentsEntries,
            );
            $pages = $document->pages;
        }

        $debugger->lifecycle('document.created', [
            'title' => $document->title,
            'page_count' => count($pages),
            'profile' => $document->profile->name(),
        ]);

        foreach ($pages as $index => $page) {
            $debugger->lifecycle('page.added', [
                'page' => $index + 1,
                'page_count' => count($pages),
                'width' => round($page->size->width(), 3),
                'height' => round($page->size->height(), 3),
            ]);
        }

        return $document;
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
        $document = $this->build();
        $scope = $document->debugger->startPerformanceScope('file.write', [
            'path' => $path,
            'page_count' => count($document->pages),
        ]);
        $output = new FileOutput($path);

        try {
            new DocumentRenderer()->write($document, $output);
            $output->close();
            $scope->stop([
                'path' => $path,
                'bytes' => $output->offset(),
            ]);
        } catch (Throwable $throwable) {
            unset($output);

            throw $throwable;
        }
    }

    private function buildDebugger(): Debugger
    {
        if ($this->debugConfig === null) {
            return Debugger::disabled();
        }

        return new Debugger(
            config: $this->debugConfig,
            sink: $this->debugSink ?? $this->debugConfig->sink ?? new NullDebugSink(),
        );
    }

    private function debugger(): Debugger
    {
        return $this->buildDebugger();
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

    public function buildPageDecorationResult(): Page
    {
        return $this->buildCurrentPage();
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
        $this->currentPageCursorYIsTopBoundary = false;
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
        bool $artifact = false,
    ): array {
        $contents = [];
        $annotations = [];
        $availableWidth = $textFlow->availableTextWidthFrom($x, $options);
        $fontRunMapper = $this->fontRunMapper();
        $textBlockBuilder = $this->textBlockBuilder();
        $scope = $this->debugger()->startPerformanceScope('text.content', [
            'line_count' => count($shapedLines),
            'font_type' => $font instanceof EmbeddedFontDefinition ? 'embedded' : 'standard',
        ]);

        foreach ($shapedLines as $index => $lineRuns) {
            if ($lineRuns === []) {
                continue;
            }

            $runY = $y - ($textFlow->lineHeight($options) * $index);
            $isFirstLineOfParagraph = $this->isFirstLineOfParagraph($wrappedLines, $index);
            $lineBaseX = $textFlow->lineX($x, $options, $isFirstLineOfParagraph);
            $mappedRuns = [];

            foreach ($lineRuns as $run) {
                $mappedRun = $fontRunMapper->map(
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
                $textBlockContent = $textBlockBuilder->build(
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

        if ($contentsString !== '' && $artifact && $this->requiresTaggedPdfProfile()) {
            $contentsString = $this->wrapArtifactGraphics($contentsString);
        } elseif ($contentsString !== '' && $markedContentTag !== null && $markedContentId !== null) {
            $contentsString = $this->wrapMarkedContent($markedContentTag, $markedContentId, $contentsString);
        }

        $scope->stop([
            'line_count' => count($shapedLines),
            'font_type' => $font instanceof EmbeddedFontDefinition ? 'embedded' : 'standard',
            'annotation_count' => count($annotations),
            'content_length' => strlen($contentsString),
        ]);

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
        float $pdfVersion,
        ?string $markedContentTag = null,
        ?int $markedContentId = null,
        bool $artifact = false,
    ): array {
        $contents = [];
        $annotations = [];
        $availableWidth = $textFlow->availableTextWidthFrom($x, $options);
        $currentPageIndex = count($this->pages);
        $nextLinkGroupId = 0;
        $continuingLinkGroup = null;
        $fontRunMapper = $this->fontRunMapper();
        $textShaper = $this->textShaper();
        $textBlockBuilder = $this->textBlockBuilder();
        $defaultSegmentFont = $options->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($options->embeddedFont)
            : StandardFontDefinition::from($options->fontName);
        /** @var array<string, array{runs: list<ShapedTextRun>, renderState: array{fontAlias: string, embeddedPageFont: ?PageFont, useHexString: bool}}> $defaultSegmentCache */
        $defaultSegmentCache = [];
        $scope = $this->debugger()->startPerformanceScope('text.content.segments', [
            'line_count' => count($wrappedSegmentLines),
        ]);

        foreach ($wrappedSegmentLines as $index => $lineSegments) {
            if ($lineSegments === []) {
                $continuingLinkGroup = null;
                continue;
            }

            $runY = $y - ($textFlow->lineHeight($options) * $index);
            $isFirstLineOfParagraph = $this->isFirstSegmentLineOfParagraph($wrappedSegmentLines, $index);
            $lineBaseX = $textFlow->lineX($x, $options, $isFirstLineOfParagraph);
            /** @var list<array{mappedRun: MappedTextRun, link: LinkTarget|TextLink|null, options: TextOptions, font: StandardFontDefinition|EmbeddedFontDefinition, fontAlias: string}> $lineEntries */
            $lineEntries = [];
            $segmentScope = $this->debugger()->startPerformanceScope('text.content.segments.segment_runs', [
                'segment_count' => count($lineSegments),
            ]);

            foreach ($lineSegments as $segment) {
                if ($segment->text === '') {
                    continue;
                }

                if ($segment->options === null) {
                    $segmentOptions = $options;
                    $segmentFont = $defaultSegmentFont;
                    $cacheKey = $segment->text . "\0" . $segmentOptions->baseDirection->value;

                    if (!isset($defaultSegmentCache[$cacheKey])) {
                        $segmentRuns = $textShaper->shape($segment->text, $segmentOptions->baseDirection, $segmentFont);
                        $defaultSegmentCache[$cacheKey] = [
                            'runs' => $segmentRuns,
                            'renderState' => $this->prepareTextRenderState(
                                $segment->text,
                                $segmentOptions,
                                $segmentFont,
                                [$segmentRuns],
                            ),
                        ];
                    }

                    $segmentRuns = $defaultSegmentCache[$cacheKey]['runs'];
                    $segmentRenderState = $defaultSegmentCache[$cacheKey]['renderState'];
                } else {
                    $segmentOptions = $this->textOptionsForSegment($options, $segment);
                    $segmentFont = $segmentOptions->embeddedFont !== null
                        ? EmbeddedFontDefinition::fromSource($segmentOptions->embeddedFont)
                        : StandardFontDefinition::from($segmentOptions->fontName);
                    $segmentRuns = $textShaper->shape($segment->text, $segmentOptions->baseDirection, $segmentFont);
                    $segmentRenderState = $this->prepareTextRenderState(
                        $segment->text,
                        $segmentOptions,
                        $segmentFont,
                        [$segmentRuns],
                    );
                }

                foreach ($segmentRuns as $run) {
                    $mappedRun = $fontRunMapper->map(
                        $run,
                        $segmentFont,
                        $segmentOptions,
                        $pdfVersion,
                        $segmentRenderState['embeddedPageFont'],
                        $segmentRenderState['useHexString'],
                    );

                    if ($mappedRun->text === '') {
                        continue;
                    }

                    $lineEntries[] = [
                        'mappedRun' => $mappedRun,
                        'link' => $segment->link,
                        'options' => $segmentOptions,
                        'font' => $segmentFont,
                        'fontAlias' => $segmentRenderState['fontAlias'],
                    ];
                }
            }

            $segmentScope->stop([
                'entry_count' => count($lineEntries),
            ]);

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
            $renderScope = $this->debugger()->startPerformanceScope('text.content.segments.line_render', [
                'entry_count' => count($lineEntries),
            ]);
            $renderBlockScope = $this->debugger()->startPerformanceScope('text.content.segments.line_render.block', [
                'entry_count' => count($lineEntries),
            ]);

            foreach ($lineEntries as $lineEntry) {
                /** @var array{mappedRun: MappedTextRun, link: LinkTarget|TextLink|null, options: TextOptions, font: StandardFontDefinition|EmbeddedFontDefinition, fontAlias: string} $lineEntry */
                /** @var MappedTextRun $mappedRun */
                $mappedRun = $lineEntry['mappedRun'];
                /** @var LinkTarget|TextLink|null $link */
                $link = $lineEntry['link'] ?? null;
                /** @var TextOptions $segmentOptions */
                $segmentOptions = $lineEntry['options'];
                /** @var StandardFontDefinition|EmbeddedFontDefinition $segmentFont */
                $segmentFont = $lineEntry['font'];
                /** @var string $segmentFontAlias */
                $segmentFontAlias = $lineEntry['fontAlias'];
                $renderedEntries[] = [
                    'mappedRun' => $mappedRun,
                    'link' => $link,
                    'font' => $segmentFont,
                    'options' => $segmentOptions,
                    'x' => $runX,
                    'textBlockContent' => $textBlockBuilder->build(
                        $mappedRun->encodedText,
                        $segmentOptions,
                        $runX,
                        $runY,
                        $segmentFontAlias,
                        $segmentFont,
                        $mappedRun->glyphNames,
                        $mappedRun->textAdjustments,
                        $mappedRun->positionedFragments,
                        $mappedRun->useHexString,
                    ),
                ];
                $runX += $mappedRun->width;
            }

            $renderBlockScope->stop([
                'rendered_entry_count' => count($renderedEntries),
            ]);

            $renderScope->stop([
                'rendered_entry_count' => count($renderedEntries),
            ]);

            $mergeScope = $this->debugger()->startPerformanceScope('text.content.segments.merge', [
                'entry_count' => count($renderedEntries),
            ]);
            $mergedRenderedEntries = $this->mergeRenderedSegmentEntries($renderedEntries);
            $mergeScope->stop([
                'merged_entry_count' => count($mergedRenderedEntries),
            ]);
            $lastLinkedGroupOnLine = null;
            $linkWrapScope = $this->debugger()->startPerformanceScope('text.content.segments.line_render.link_wrap', [
                'entry_count' => count($mergedRenderedEntries),
            ]);

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
                        $textFlow->lineHeight($renderedEntry['options']),
                        $renderedEntry['font']->ascent($renderedEntry['options']->fontSize),
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

            $linkWrapScope->stop([
                'annotation_count' => count($annotations),
            ]);

            $continuingLinkGroup = $lastLinkedGroupOnLine;
        }

        $contentsString = implode("\n", $contents);

        if ($contentsString !== '' && $artifact && $this->requiresTaggedPdfProfile()) {
            $contentsString = $this->wrapArtifactGraphics($contentsString);
        } elseif ($contentsString !== '' && $markedContentTag !== null && $markedContentId !== null) {
            $contentsString = $this->wrapMarkedContent($markedContentTag, $markedContentId, $contentsString);
        }

        $scope->stop([
            'line_count' => count($wrappedSegmentLines),
            'annotation_count' => count($annotations),
            'content_length' => strlen($contentsString),
        ]);

        return [
            'contents' => $contentsString,
            'annotations' => $annotations,
        ];
    }

    /**
     * @param list<array{mappedRun: MappedTextRun, link: LinkTarget|TextLink|null, font: StandardFontDefinition|EmbeddedFontDefinition, options: TextOptions, x: float, textBlockContent: string}> $renderedEntries
     * @return list<array{link: LinkTarget|TextLink|null, font: StandardFontDefinition|EmbeddedFontDefinition, options: TextOptions, x: float, width: float, text: string, textBlockContent: string}>
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
                if ($renderedEntry['font']->ascent($renderedEntry['options']->fontSize) > $mergedEntries[$lastIndex]['font']->ascent($mergedEntries[$lastIndex]['options']->fontSize)) {
                    $mergedEntries[$lastIndex]['font'] = $renderedEntry['font'];
                    $mergedEntries[$lastIndex]['options'] = $renderedEntry['options'];
                }

                continue;
            }

            $mergedEntries[] = [
                'link' => $link,
                'font' => $renderedEntry['font'],
                'options' => $renderedEntry['options'],
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
        $renderState = $this->prepareTextRenderState(
            $caption->text,
            $captionLayout['textOptions'],
            $font,
            $shapedLines,
            ($this->profile ?? Profile::standard())->requiresExtractableEmbeddedUnicodeFonts(),
        );
        $markedContentId = $taggedTableId !== null ? $this->nextTaggedMarkedContentId() : null;
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

        if ($taggedTableId !== null && $markedContentId !== null) {
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
        $scope = $this->debugger()->startPerformanceScope('text.content.link', [
            'width' => round($width, 3),
            'has_tagged_group' => $taggedGroupKey !== null ? 1 : 0,
        ]);
        $markedContentId = $this->requiresTaggedLinkAnnotations()
            ? $this->nextMarkedContentId()
            : null;

        if ($markedContentId !== null) {
            $textBlockContent = implode("\n", [
                '/Link << /MCID ' . $markedContentId . ' >> BDC',
                $textBlockContent,
                'EMC',
            ]);
        }

        $result = [
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

        $scope->stop([
            'content_length' => strlen($textBlockContent),
            'has_marked_content_id' => $markedContentId !== null ? 1 : 0,
        ]);

        return $result;
    }

    private function textOptionsWithLink(TextOptions $options, LinkTarget | TextLink | null $link): TextOptions
    {
        return TextOptions::make(
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
            tag: $options->tag,
            semantic: $options->semantic,
        );
    }

    private function textOptionsForSegment(TextOptions $options, TextSegment $segment): TextOptions
    {
        if ($segment->options === null) {
            return $this->textOptionsWithLink($options, $segment->link);
        }

        $this->assertInlineSegmentTextOptions($segment->options);

        return TextOptions::make(
            x: $options->x,
            y: $options->y,
            width: $options->width,
            maxWidth: $options->maxWidth,
            fontSize: $this->segmentFontSize($options, $segment->options),
            lineHeight: $options->lineHeight,
            spacingBefore: $options->spacingBefore,
            spacingAfter: $options->spacingAfter,
            fontName: $this->segmentFontName($options, $segment->options),
            embeddedFont: $segment->options->embeddedFont ?? $options->embeddedFont,
            fontEncoding: $segment->options->fontEncoding ?? $options->fontEncoding,
            color: $segment->options->color ?? $options->color,
            kerning: $this->segmentKerning($options, $segment->options),
            baseDirection: $this->segmentBaseDirection($options, $segment->options),
            align: $options->align,
            firstLineIndent: $options->firstLineIndent,
            hangingIndent: $options->hangingIndent,
            link: $segment->link,
            tag: $options->tag,
            semantic: $options->semantic,
        );
    }

    private function assertInlineSegmentTextOptions(TextOptions $options): void
    {
        if (
            $options->x !== null
            || $options->y !== null
            || $options->width !== null
            || $options->maxWidth !== null
            || $options->lineHeight !== null
            || $options->spacingBefore !== null
            || $options->spacingAfter !== null
            || $options->align !== TextAlign::LEFT
            || $options->firstLineIndent !== 0.0
            || $options->hangingIndent !== 0.0
            || $options->link !== null
            || $options->tag !== null
            || $options->semantic !== TextSemantic::CONTENT
        ) {
            throw new InvalidArgumentException('TextSegment options only support inline text overrides.');
        }
    }

    private function segmentFontSize(TextOptions $baseOptions, TextOptions $segmentOptions): float
    {
        if ($segmentOptions->fontSize === 18.0 && $baseOptions->fontSize !== 18.0) {
            return $baseOptions->fontSize;
        }

        return $segmentOptions->fontSize;
    }

    private function segmentFontName(TextOptions $baseOptions, TextOptions $segmentOptions): string
    {
        if ($segmentOptions->fontName === StandardFont::HELVETICA->value && $baseOptions->fontName !== StandardFont::HELVETICA->value) {
            return $baseOptions->fontName;
        }

        return $segmentOptions->fontName;
    }

    private function segmentKerning(TextOptions $baseOptions, TextOptions $segmentOptions): bool
    {
        if ($segmentOptions->kerning && !$baseOptions->kerning) {
            return $baseOptions->kerning;
        }

        return $segmentOptions->kerning;
    }

    private function segmentBaseDirection(TextOptions $baseOptions, TextOptions $segmentOptions): TextDirection
    {
        if ($segmentOptions->baseDirection === TextDirection::LTR && $baseOptions->baseDirection !== TextDirection::LTR) {
            return $baseOptions->baseDirection;
        }

        return $segmentOptions->baseDirection;
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
        $renderState = $this->prepareTextRenderState(
            $cellLayout->cell->text,
            $cellLayout->textOptions,
            $font,
            $shapedLines,
            ($this->profile ?? Profile::standard())->requiresExtractableEmbeddedUnicodeFonts(),
        );
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
            ? $this->nextTaggedMarkedContentId()
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
        return $this->wrapArtifactGraphics(implode("\n", [
            'q',
            $this->buildFillColorOperator($backgroundColor),
            $this->formatNumber($x) . ' ' . $this->formatNumber($topY - $height) . ' '
            . $this->formatNumber($width) . ' ' . $this->formatNumber($height) . ' re',
            'f',
            'Q',
        ]));
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
        $this->attachTaggedStructureChildKey('table:' . $tableId);

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
        $this->taggedTables[$tableId]['captionReferences'][] = $this->taggedContentReference($markedContentId);
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
        $this->taggedTables[$tableId][$sectionKey][$rowIndex]['cells'][$columnIndex]['references'][] = $this->taggedContentReference($markedContentId);
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
                key: 'table:' . $tableId,
            );
        }

        return $taggedTables;
    }

    /**
     * @return list<TaggedFigure>
     */
    private function buildTaggedFigures(): array
    {
        return array_map(
            static fn (array $figure): TaggedFigure => new TaggedFigure(
                pageIndex: $figure['pageIndex'],
                markedContentId: $figure['markedContentId'],
                altText: $figure['altText'],
                key: $figure['key'],
            ),
            $this->taggedFigures,
        );
    }

    /**
     * @return list<TaggedTextBlock>
     */
    private function buildTaggedTextBlocks(): array
    {
        return array_map(
            static fn (array $block): TaggedTextBlock => new TaggedTextBlock(
                tag: $block['tag'],
                pageIndex: $block['pageIndex'],
                markedContentId: $block['markedContentId'],
                key: $block['key'],
            ),
            $this->taggedTextBlocks,
        );
    }

    /**
     * @return list<TaggedList>
     */
    private function buildTaggedLists(): array
    {
        $taggedLists = [];

        foreach ($this->taggedLists as $listId => $items) {
            $taggedLists[] = new TaggedList(
                listId: $listId,
                items: array_map(
                    static fn (array $item): TaggedListItem => new TaggedListItem(
                        labelReference: new TaggedListContentReference(
                            $item['label']['pageIndex'],
                            $item['label']['markedContentId'],
                        ),
                        bodyReference: new TaggedListContentReference(
                            $item['body']['pageIndex'],
                            $item['body']['markedContentId'],
                        ),
                    ),
                    $items,
                ),
                key: 'list:' . $listId,
            );
        }

        return $taggedLists;
    }

    private function registerTaggedTextBlock(string $tag, int $markedContentId): void
    {
        $key = 'text:' . count($this->taggedTextBlocks);
        $this->taggedTextBlocks[] = [
            'key' => $key,
            'tag' => $tag,
            ...$this->taggedContentReference($markedContentId),
        ];
        $this->attachTaggedStructureChildKey($key);
    }

    private function registerTaggedFigure(int $markedContentId, ?string $altText): void
    {
        $key = 'figure:graphics:' . count($this->taggedFigures);
        $this->taggedFigures[] = [
            'key' => $key,
            ...$this->taggedContentReference($markedContentId),
            'altText' => $altText,
        ];
        $this->attachTaggedStructureChildKey($key);
    }

    private function registerTaggedListItem(int $listId, int $labelMarkedContentId, int $bodyMarkedContentId): void
    {
        if (!isset($this->taggedLists[$listId])) {
            $this->attachTaggedStructureChildKey('list:' . $listId);
        }

        $this->taggedLists[$listId][] = [
            'label' => $this->taggedContentReference($labelMarkedContentId),
            'body' => $this->taggedContentReference($bodyMarkedContentId),
        ];
    }

    /**
     * @return list<TaggedStructureElement>
     */
    private function buildTaggedStructureElements(): array
    {
        $elements = [];

        foreach ($this->taggedStructureElements as $key => $element) {
            $elements[] = new TaggedStructureElement($key, $element['tag'], $element['childKeys']);
        }

        return $elements;
    }

    private function attachTaggedStructureChildKey(string $key): void
    {
        if (!$this->requiresTaggedStructure()) {
            return;
        }

        $containerKey = $this->taggedStructureStack[count($this->taggedStructureStack) - 1] ?? null;

        if ($containerKey === null) {
            $this->taggedDocumentChildKeys[] = $key;

            return;
        }

        $this->taggedStructureElements[$containerKey]['childKeys'][] = $key;
    }

    private function resolveTaggedTextTag(?TextOptions $options, ?string $defaultTag = null): ?string
    {
        if ($options?->semantic === TextSemantic::ARTIFACT) {
            if ($options->tag !== null) {
                throw new InvalidArgumentException('Text options cannot combine semantic artifact with a tagged text role.');
            }

            return null;
        }

        $tag = $options?->tag->value ?? $defaultTag;

        if ($tag === null) {
            return $this->requiresTaggedStructure() ? 'P' : null;
        }

        $registry = new TaggedStructureRoleRegistry();
        $registry->assertKnownTag($tag);

        if (!in_array($tag, $registry->supportedLeafTextTags(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Tagged text tag "%s" is not supported for text content.',
                $tag,
            ));
        }

        return $this->requiresTaggedStructure() ? $tag : null;
    }

    private function requiresTaggedStructure(): bool
    {
        if ($this->renderingPageDecoration) {
            return false;
        }

        return ($this->profile ?? Profile::standard())->requiresTaggedPdf();
    }

    private function requiresTaggedPdfProfile(): bool
    {
        return ($this->profile ?? Profile::standard())->requiresTaggedPdf();
    }

    private function requiresTaggedLinkAnnotations(): bool
    {
        if ($this->renderingPageDecoration) {
            return false;
        }

        return ($this->profile ?? Profile::standard())->requiresTaggedLinkAnnotations();
    }

    private function profileOrDefault(): Profile
    {
        return $this->profile ?? Profile::standard();
    }

    private function resolveCurrentPageAnnotationReference(PageAnnotationReference $reference): int
    {
        $currentPageNumber = count($this->pages) + 1;

        if ($reference->pageNumber !== $currentPageNumber) {
            throw new InvalidArgumentException(sprintf(
                'Page annotation reference targets page %d, but the current page is %d.',
                $reference->pageNumber,
                $currentPageNumber,
            ));
        }

        if (!isset($this->currentPageAnnotations[$reference->annotationIndex])) {
            throw new InvalidArgumentException(sprintf(
                'Page annotation %d does not exist on the current page.',
                $reference->annotationIndex + 1,
            ));
        }

        return $reference->annotationIndex;
    }

    private function reuseOrAppendAttachment(FileAttachment $candidate): FileAttachment
    {
        $existingAttachment = $this->findAttachmentByFilename($candidate->filename);

        if ($existingAttachment === null) {
            $this->attachments[] = $candidate;

            return $candidate;
        }

        if (!$this->attachmentsAreEquivalent($existingAttachment, $candidate)) {
            throw new InvalidArgumentException(sprintf(
                'Attachment "%s" already exists with different contents or metadata.',
                $candidate->filename,
            ));
        }

        return $existingAttachment;
    }

    private function findAttachmentByFilename(string $filename): ?FileAttachment
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment->filename === $filename) {
                return $attachment;
            }
        }

        return null;
    }

    private function attachmentsAreEquivalent(FileAttachment $left, FileAttachment $right): bool
    {
        return $left->filename === $right->filename
            && $left->embeddedFile->contents === $right->embeddedFile->contents
            && $left->embeddedFile->mimeType === $right->embeddedFile->mimeType
            && $left->description === $right->description
            && $left->associatedFileRelationship === $right->associatedFileRelationship;
    }

    private function nextTaggedMarkedContentId(): ?int
    {
        return $this->requiresTaggedStructure() ? $this->nextMarkedContentId() : null;
    }

    private function advanceCursorToGraphicTop(float $y): void
    {
        $this->currentPageCursorY = $this->currentPageCursorY !== null
            ? min($this->currentPageCursorY, $y)
            : $y;
        $this->currentPageCursorYIsTopBoundary = true;
    }

    /**
     * @return array{pageIndex: int, markedContentId: int}
     */
    private function taggedContentReference(int $markedContentId): array
    {
        return [
            'pageIndex' => count($this->pages),
            'markedContentId' => $markedContentId,
        ];
    }

    private function renderTextBlock(string $text, ?TextOptions $options, ?string $taggedTextTag): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= TextOptions::make();
        $artifact = $options->semantic === TextSemantic::ARTIFACT;
        $font = $options->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($options->embeddedFont)
            : StandardFontDefinition::from($options->fontName);
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($options, $font);
        $debugger = $clone->debugger();
        $wrapScope = $debugger->startPerformanceScope('text.wrap', [
            'mode' => 'block',
        ]);
        $wrappedLines = $textFlow->wrapTextLines($text, $options, $font, $placement['x']);
        $wrapScope->stop([
            'mode' => 'block',
            'line_count' => count($wrappedLines),
            'text_length' => strlen($text),
        ]);
        $shapedLines = $clone->shapeWrappedTextLines($wrappedLines, $options, $font);
        $renderState = $clone->prepareTextRenderState($text, $options, $font, $shapedLines);
        $markedContentTag = $taggedTextTag !== null && $clone->requiresTaggedStructure()
            ? $taggedTextTag
            : null;
        $markedContentId = $markedContentTag !== null ? $clone->nextTaggedMarkedContentId() : null;

        $textResult = $clone->buildWrappedTextContent(
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
            $markedContentTag,
            $markedContentId,
            $artifact,
        );
        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $textResult['contents'],
        );
        $clone->currentPageAnnotations = [...$clone->currentPageAnnotations, ...$textResult['annotations']];
        $clone->currentPageCursorY = $textFlow->nextCursorY($options, $placement['y'], count($wrappedLines));
        $clone->currentPageCursorYIsTopBoundary = false;

        if ($markedContentTag !== null && $markedContentId !== null && $textResult['contents'] !== '') {
            $clone->registerTaggedTextBlock($markedContentTag, $markedContentId);
        }

        return $clone;
    }

    /**
     * @param list<string|TextSegment> $lines
     */
    private function renderTextLines(array $lines, ?TextOptions $options, ?string $taggedTextTag): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= TextOptions::make();
        $artifact = $options->semantic === TextSemantic::ARTIFACT;
        $debugger = $clone->debugger();

        if ($lines === []) {
            return $clone;
        }

        $validatedLines = [];
        $validatedSegments = [];
        $containsSegments = false;

        foreach ($lines as $line) {
            if ($line instanceof TextSegment) {
                $validatedSegments[] = $line;
                $validatedLines[] = $line->text;
                $containsSegments = true;

                continue;
            }

            $validatedLines[] = $line;
            $validatedSegments[] = new TextSegment($line);
        }

        $font = $options->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($options->embeddedFont)
            : StandardFontDefinition::from($options->fontName);
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($options, $font);
        $markedContentTag = $taggedTextTag !== null && $clone->requiresTaggedStructure()
            ? $taggedTextTag
            : null;
        $markedContentId = $markedContentTag !== null ? $clone->nextTaggedMarkedContentId() : null;

        if ($containsSegments) {
            $wrappedSegmentLines = $clone->wrapExplicitTextSegmentLines($validatedSegments, $options, $font, $textFlow, $placement['x']);
            $textResult = $clone->buildWrappedTextSegmentsContent(
                $wrappedSegmentLines,
                $options,
                $textFlow,
                $placement['x'],
                $placement['y'],
                ($this->profile ?? Profile::standard())->version(),
                $markedContentTag,
                $markedContentId,
                $artifact,
            );
            $lineCount = count($wrappedSegmentLines);
        } else {
            $wrapScope = $debugger->startPerformanceScope('text.wrap', [
                'mode' => 'lines',
            ]);
            $wrappedLines = $clone->wrapExplicitTextLines($validatedLines, $options, $font, $textFlow, $placement['x']);
            $wrapScope->stop([
                'mode' => 'lines',
                'line_count' => count($wrappedLines),
                'text_length' => strlen(implode('', $validatedLines)),
            ]);
            $shapedLines = $clone->shapeWrappedTextLines($wrappedLines, $options, $font);
            $renderState = $clone->prepareTextRenderState(implode('', $validatedLines), $options, $font, $shapedLines);
            $textResult = $clone->buildWrappedTextContent(
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
                $markedContentTag,
                $markedContentId,
                $artifact,
            );
            $lineCount = count($wrappedLines);
        }

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $textResult['contents'],
        );
        $clone->currentPageAnnotations = [...$clone->currentPageAnnotations, ...$textResult['annotations']];
        $clone->currentPageCursorY = $textFlow->nextCursorY($options, $placement['y'], $lineCount);
        $clone->currentPageCursorYIsTopBoundary = false;

        if ($markedContentTag !== null && $markedContentId !== null && $textResult['contents'] !== '') {
            $clone->registerTaggedTextBlock($markedContentTag, $markedContentId);
        }

        return $clone;
    }

    /**
     * @return array{contents: string, annotations: list<PageAnnotation>, lineCount: int}
     */
    private function renderTextBlockAt(
        string $text,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        TextFlow $textFlow,
        float $x,
        float $y,
        ?string $markedContentTag,
        ?int $markedContentId,
    ): array {
        $artifact = $options->semantic === TextSemantic::ARTIFACT;
        $debugger = $this->debugger();
        $wrapScope = $debugger->startPerformanceScope('text.wrap', [
            'mode' => 'block_at',
        ]);
        $wrappedLines = $textFlow->wrapTextLines($text, $options, $font, $x);
        $wrapScope->stop([
            'mode' => 'block_at',
            'line_count' => count($wrappedLines),
            'text_length' => strlen($text),
        ]);
        $shapedLines = $this->shapeWrappedTextLines($wrappedLines, $options, $font);
        $renderState = $this->prepareTextRenderState(
            $text,
            $options,
            $font,
            $shapedLines,
            ($this->profile ?? Profile::standard())->isPdfA() && $font instanceof EmbeddedFontDefinition,
        );
        $textResult = $this->buildWrappedTextContent(
            $wrappedLines,
            $shapedLines,
            $options,
            $textFlow,
            $x,
            $y,
            $renderState['fontAlias'],
            $font,
            $renderState['embeddedPageFont'],
            $renderState['useHexString'],
            ($this->profile ?? Profile::standard())->version(),
            $markedContentTag,
            $markedContentId,
            $artifact,
        );

        return [
            'contents' => $textResult['contents'],
            'annotations' => $textResult['annotations'],
            'lineCount' => count($wrappedLines),
        ];
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function wrapExplicitTextLines(
        array $lines,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        TextFlow $textFlow,
        float $x,
    ): array {
        $wrappedLines = [];

        foreach ($lines as $line) {
            if ($line === '') {
                $wrappedLines[] = '';

                continue;
            }

            $wrappedLines = [
                ...$wrappedLines,
                ...$textFlow->wrapTextLines($line, $options, $font, $x),
            ];
        }

        return $wrappedLines;
    }

    /**
     * @param list<TextSegment> $lines
     * @return list<list<TextSegment>>
     */
    private function wrapExplicitTextSegmentLines(
        array $lines,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        TextFlow $textFlow,
        float $x,
    ): array {
        $wrappedLines = [];

        foreach ($lines as $line) {
            if ($line->text === '') {
                $wrappedLines[] = [];

                continue;
            }

            $wrappedLines = [
                ...$wrappedLines,
                ...$textFlow->wrapSegmentLines([$line], $options, $font, $x),
            ];
        }

        return $wrappedLines;
    }

    private function copyTextOptions(
        TextOptions $options,
        ?float $x = null,
        ?float $y = null,
        ?float $width = null,
        ?float $spacingBefore = null,
        ?float $spacingAfter = null,
    ): TextOptions {
        return TextOptions::make(
            x: $x,
            y: $y,
            width: $width,
            maxWidth: $options->maxWidth,
            fontSize: $options->fontSize,
            lineHeight: $options->lineHeight,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
            fontName: $options->fontName,
            embeddedFont: $options->embeddedFont,
            fontEncoding: $options->fontEncoding,
            color: $options->color,
            kerning: $options->kerning,
            baseDirection: $options->baseDirection,
            align: $options->align,
            firstLineIndent: $options->firstLineIndent,
            hangingIndent: $options->hangingIndent,
            link: $options->link,
            tag: $options->tag,
            semantic: $options->semantic,
        );
    }

    private function listItemLabel(ListOptions $list, int $index): string
    {
        return match ($list->type) {
            ListType::BULLET => $list->marker ?? "\xE2\x80\xA2",
            ListType::NUMBERED => sprintf($list->marker ?? '%d.', $list->start + $index),
        };
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

        return TextOptions::make(
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
            tag: $captionOptions->tag,
            semantic: $captionOptions->semantic,
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
            throw new DocumentValidationException(
                DocumentBuildError::TABLE_LAYOUT_INVALID,
                'Table placement x must not start left of the page content area.',
            );
        }

        if (($table->placement->x + $table->placement->width) > $contentArea->right) {
            throw new DocumentValidationException(
                DocumentBuildError::TABLE_LAYOUT_INVALID,
                'Table placement width exceeds the page content area.',
            );
        }

        if ($table->placement->y !== null && ($table->placement->y > $contentArea->top || $table->placement->y < $contentArea->bottom)) {
            throw new DocumentValidationException(
                DocumentBuildError::TABLE_LAYOUT_INVALID,
                'Table placement y must stay within the page content area.',
            );
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
        bool $forceUnicodeEmbeddedFont = false,
    ): array {
        $scope = $this->debugger()->startPerformanceScope('text.render_state', [
            'line_count' => count($shapedLines),
            'font_type' => $font instanceof EmbeddedFontDefinition ? 'embedded' : 'standard',
        ]);
        $encodableText = $this->textForEmbeddedFontEncodingChecks($text);
        $usesUnicodeEmbeddedFont = $font instanceof EmbeddedFontDefinition
            && (
                $forceUnicodeEmbeddedFont
                ||
                !$font->supportsText($encodableText)
                || $this->containsShapedEmbeddedGlyphIds($shapedLines)
            );

        if ($font instanceof EmbeddedFontDefinition && $usesUnicodeEmbeddedFont && !$font->supportsUnicodeText($encodableText)) {
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

        $scope->stop([
            'line_count' => count($shapedLines),
            'font_type' => $font instanceof EmbeddedFontDefinition ? 'embedded' : 'standard',
            'text_length' => strlen($text),
            'use_hex_string' => $usesUnicodeEmbeddedFont ? 1 : 0,
        ]);

        return [
            'fontAlias' => $fontAlias,
            'embeddedPageFont' => $font instanceof EmbeddedFontDefinition
                ? $this->currentPageFontResources[$fontAlias] ?? null
                : null,
            'useHexString' => $usesUnicodeEmbeddedFont,
        ];
    }

    private function textForEmbeddedFontEncodingChecks(string $text): string
    {
        return str_replace(["\r\n", "\r", "\n"], '', $text);
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
        return $this->wrapArtifactGraphics(implode("\n", [
            'q',
            $this->formatNumber($width) . ' w',
            $this->formatNumber($x1) . ' ' . $this->formatNumber($y1) . ' m',
            $this->formatNumber($x2) . ' ' . $this->formatNumber($y2) . ' l',
            'S',
            'Q',
        ]));
    }

    private function wrapArtifactGraphics(string $contents): string
    {
        if ($contents === '' || !$this->requiresTaggedPdfProfile()) {
            return $contents;
        }

        // Borders and fills used only for layout are artifacts, not logical document content.
        return implode("\n", [
            '/Artifact BMC',
            $contents,
            'EMC',
        ]);
    }

    private function buildGraphicsContent(
        string $contents,
        ?GraphicsAccessibility $accessibility = null,
        ?int $markedContentId = null,
    ): string {
        if (!$this->requiresTaggedPdfProfile()) {
            return $contents;
        }

        if ($accessibility?->decorative === true || $accessibility === null || $markedContentId === null) {
            return $this->wrapArtifactGraphics($contents);
        }

        return implode("\n", [
            '/Figure << /MCID ' . $markedContentId . ' >> BDC',
            $contents,
            'EMC',
        ]);
    }

    /**
     * @return list<string>
     */
    private function buildStrokeStyleOperators(StrokeStyle $stroke): array
    {
        $operators = [];

        if ($stroke->color !== null) {
            $operators[] = $this->colorStrokeOperator($stroke->color);
        }

        $operators[] = $this->formatNumber($stroke->width) . ' w';

        return $operators;
    }

    private function buildRectangleContent(
        float $x,
        float $y,
        float $width,
        float $height,
        ?StrokeStyle $stroke,
        ?Color $fillColor,
    ): string {
        $lines = ['q'];

        if ($stroke !== null) {
            $lines = [...$lines, ...$this->buildStrokeStyleOperators($stroke)];
        }

        if ($fillColor !== null) {
            $lines[] = $this->colorFillOperator($fillColor);
        }

        $lines[] = $this->formatNumber($x) . ' ' . $this->formatNumber($y) . ' '
            . $this->formatNumber($width) . ' ' . $this->formatNumber($height) . ' re';
        $lines[] = $this->graphicsPaintOperator($stroke, $fillColor);
        $lines[] = 'Q';

        return implode("\n", $lines);
    }

    private function buildPathContent(Path $path, ?StrokeStyle $stroke, ?Color $fillColor): string
    {
        $lines = ['q'];

        if ($stroke !== null) {
            $lines = [...$lines, ...$this->buildStrokeStyleOperators($stroke)];
        }

        if ($fillColor !== null) {
            $lines[] = $this->colorFillOperator($fillColor);
        }

        foreach ($path->commands() as $command) {
            $values = array_map($this->formatNumber(...), $command['values']);
            $lines[] = $values === []
                ? $command['operator']
                : implode(' ', $values) . ' ' . $command['operator'];
        }

        $lines[] = $this->graphicsPaintOperator($stroke, $fillColor);
        $lines[] = 'Q';

        return implode("\n", $lines);
    }

    private function graphicsPaintOperator(?StrokeStyle $stroke, ?Color $fillColor): string
    {
        if ($stroke !== null && $fillColor !== null) {
            return 'B';
        }

        if ($fillColor !== null) {
            return 'f';
        }

        return 'S';
    }

    private function buildFillColorOperator(Color $color): string
    {
        $components = array_map(
            $this->formatNumber(...),
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

        if (!$this->requiresTaggedPdfProfile()) {
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
        if (!$this->requiresTaggedStructure()) {
            return null;
        }

        if ($accessibility?->decorative === true) {
            return null;
        }

        $markedContentId = $this->currentPageNextMarkedContentId;
        $this->currentPageNextMarkedContentId++;

        return $markedContentId;
    }

    private function markedContentIdForGraphic(?GraphicsAccessibility $accessibility): ?int
    {
        if (!$this->requiresTaggedStructure()) {
            return null;
        }

        if ($accessibility === null || $accessibility->decorative) {
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
        return new TextFlow($this->buildCurrentPage(), $this->currentPageCursorY, $this->currentPageCursorYIsTopBoundary);
    }

    private function tableLayoutCalculator(): TableLayoutCalculator
    {
        return new TableLayoutCalculator();
    }

    private function textBlockBuilder(): TextBlockBuilder
    {
        if ($this->debugConfig !== null) {
            return new TextBlockBuilder($this->debugger());
        }

        /** @var TextBlockBuilder|null $builder */
        static $builder = null;

        return $builder ??= new TextBlockBuilder();
    }

    private function textShaper(): SimpleTextShaper
    {
        if ($this->debugConfig !== null) {
            return new SimpleTextShaper(
                defaultScriptTextShaper: new DefaultScriptTextShaper(debugger: $this->debugger()),
                debugger: $this->debugger(),
            );
        }

        /** @var SimpleTextShaper|null $shaper */
        static $shaper = null;

        return $shaper ??= new SimpleTextShaper();
    }

    private function fontRunMapper(): SimpleFontRunMapper
    {
        /** @var SimpleFontRunMapper|null $mapper */
        static $mapper = null;

        return $mapper ??= new SimpleFontRunMapper();
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
        $textShaper = $this->textShaper();
        $scope = $this->debugger()->startPerformanceScope('text.shape', [
            'line_count' => count($lines),
            'font_type' => $font instanceof EmbeddedFontDefinition ? 'embedded' : 'standard',
        ]);

        foreach ($lines as $line) {
            $shapedLines[] = $line === ''
                ? []
                : $textShaper->shape($line, $options->baseDirection, $font);
        }

        $scope->stop([
            'line_count' => count($lines),
            'font_type' => $font instanceof EmbeddedFontDefinition ? 'embedded' : 'standard',
        ]);

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
            $this->formatNumber(...),
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
            $this->formatNumber(...),
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

    /**
     * @param list<Page> $pages
     * @return list<Page>
     */
    private function applyPageDecorators(array $pages): array
    {
        if ($this->headerRenderers === [] && $this->footerRenderers === []) {
            return $pages;
        }

        $totalPages = count($pages);

        foreach ($pages as $index => $page) {
            $pageNumber = $index + 1;

            if ($this->headerRenderers !== []) {
                $page = $this->applyPageDecoration($page, $pageNumber, $totalPages, $this->headerRenderers, true);
            }

            if ($this->footerRenderers !== []) {
                $page = $this->applyPageDecoration($page, $pageNumber, $totalPages, $this->footerRenderers, false);
            }

            $pages[$index] = $page;
        }

        return $pages;
    }

    /**
     * @param list<callable(PageDecorationContext, int): void> $renderers
     */
    private function applyPageDecoration(
        Page $page,
        int $pageNumber,
        int $totalPages,
        array $renderers,
        bool $prependContents,
    ): Page {
        $context = new PageDecorationContext(
            $this->createPageDecorationBuilder($page),
            $page,
            $pageNumber,
            $totalPages,
        );

        foreach ($renderers as $renderer) {
            $renderer($context, $pageNumber);
        }

        $decorationPage = $context->decoratedPage();
        $decorationContents = $this->wrapArtifactGraphics($decorationPage->contents);

        return new Page(
            size: $page->size,
            contents: $prependContents
                ? $this->appendPageContent($decorationContents, $page->contents)
                : $this->appendPageContent($page->contents, $decorationContents),
            fontResources: [...$page->fontResources, ...$decorationPage->fontResources],
            imageResources: [...$page->imageResources, ...$decorationPage->imageResources],
            images: [...$page->images, ...$decorationPage->images],
            annotations: [...$page->annotations, ...$decorationPage->annotations],
            namedDestinations: [...$page->namedDestinations, ...$decorationPage->namedDestinations],
            margin: $page->margin,
            backgroundColor: $page->backgroundColor,
            label: $page->label,
            name: $page->name,
        );
    }

    private function createPageDecorationBuilder(Page $page): self
    {
        $clone = clone $this;
        $clone->pages = [];
        $clone->currentPageSize = $page->size;
        $clone->currentPageContents = '';
        $clone->currentPageFontResources = $page->fontResources;
        $clone->currentPageImageResources = $page->imageResources;
        $clone->currentPageImages = [];
        $clone->currentPageAnnotations = [];
        $clone->currentPageNamedDestinations = [];
        $clone->currentPageMargin = $page->margin;
        $clone->currentPageCursorY = null;
        $clone->currentPageBackgroundColor = $page->backgroundColor;
        $clone->currentPageLabel = $page->label;
        $clone->currentPageName = $page->name;
        $clone->currentPageNextMarkedContentId = 0;
        $clone->renderingPageDecoration = true;
        $clone->taggedTables = [];
        $clone->taggedFigures = [];
        $clone->taggedTextBlocks = [];
        $clone->taggedLists = [];
        $clone->taggedStructureElements = [];
        $clone->taggedDocumentChildKeys = [];
        $clone->taggedStructureStack = [];
        $clone->nextTaggedTableId = 0;
        $clone->nextTaggedListId = 0;
        $clone->nextTaggedStructureElementId = 0;

        return $clone;
    }

}
