<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCaption;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Internal\Layout\Table\Layout\RowGroupHeightResolver;
use Kalle\Pdf\Internal\Layout\Table\Layout\RowGroupPreparer;
use Kalle\Pdf\Internal\Layout\Table\Layout\RowPreparer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableCaptionRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableFooterRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableGroupRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableGroupSegmentRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TablePendingGroupFlow;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TablePendingGroupPaginator;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TablePendingRenderState;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableRenderContext;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableStructElemFactory;
use Kalle\Pdf\Internal\Layout\Table\Style\FooterStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\HeaderStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\RowStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Internal\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Internal\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Internal\Layout\Table\Support\TableTextMetrics;
use Kalle\Pdf\Internal\Layout\Table\TableSections;
use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Page;

class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const DEFAULT_CONTINUATION_TOP_MARGIN = 40.0;

    /** @var list<int> */
    private array $activeRowspans = [];
    private readonly float $topMargin;
    private readonly float $continuationTopMargin;
    private Page $page;
    private float $cursorY;
    private string $baseFont = 'Helvetica';
    private int $fontSize = 12;
    private float $lineHeightFactor = self::DEFAULT_LINE_HEIGHT_FACTOR;
    private TableStyle $style;
    private ?RowStyle $rowStyle = null;
    private ?HeaderStyle $headerStyle = null;
    private ?FooterStyle $footerStyle = null;
    private readonly TableStyleResolver $styleResolver;
    private readonly TableTextMetrics $textMetrics;
    private readonly RowGroupHeightResolver $rowGroupHeightResolver;
    private readonly PreparedCellRenderer $preparedCellRenderer;
    private readonly TableCaptionRenderer $captionRenderer;
    private readonly TableFooterRenderer $footerRenderer;
    private readonly TablePendingGroupFlow $pendingGroupFlow;
    private readonly TablePendingRenderState $pendingRenderState;
    private readonly TableSections $sections;
    private readonly ?StructElem $tableStructElem;
    private ?TableCaption $caption = null;

    /**
     * @param list<float|int> $columnWidths
     */
    public function __construct(
        Page $page,
        private readonly float $x,
        float $y,
        private readonly float $width,
        private readonly array $columnWidths,
        private readonly float $bottomMargin = 20.0,
    ) {
        if ($this->width <= 0) {
            throw new InvalidArgumentException('Table width must be greater than zero.');
        }

        if ($columnWidths === []) {
            throw new InvalidArgumentException('Table requires at least one column.');
        }

        foreach ($columnWidths as $columnWidth) {
            if ((float) $columnWidth <= 0) {
                throw new InvalidArgumentException('Table column widths must be greater than zero.');
            }
        }

        $totalColumnWidth = array_sum(array_map(static fn (float | int $value): float => (float) $value, $columnWidths));

        if (abs($totalColumnWidth - $this->width) > 0.001) {
            throw new InvalidArgumentException('Table column widths must add up to the table width.');
        }

        if ($bottomMargin < 0) {
            throw new InvalidArgumentException('Table bottom margin must be zero or greater.');
        }

        $this->page = $page;
        $this->cursorY = $y;
        $this->topMargin = $page->getHeight() - $y;
        $this->continuationTopMargin = min($this->topMargin, self::DEFAULT_CONTINUATION_TOP_MARGIN);
        $this->activeRowspans = array_fill(0, count($columnWidths), 0);
        $this->styleResolver = new TableStyleResolver();
        $this->textMetrics = new TableTextMetrics();
        $this->rowGroupHeightResolver = new RowGroupHeightResolver();
        $this->preparedCellRenderer = new PreparedCellRenderer(
            $this->styleResolver,
            new CellLayoutResolver($this->x, $this->columnWidths),
            new CellBoxRenderer($this->styleResolver),
            $this->textMetrics,
        );
        $this->captionRenderer = new TableCaptionRenderer();
        $this->footerRenderer = new TableFooterRenderer();
        $structElemFactory = new TableStructElemFactory();
        $groupRenderer = new TableGroupRenderer();
        $groupSegmentRenderer = new TableGroupSegmentRenderer(
            $this->styleResolver,
            $structElemFactory,
        );
        $pendingGroupPaginator = new TablePendingGroupPaginator();
        $this->pendingGroupFlow = new TablePendingGroupFlow(
            $pendingGroupPaginator,
            $groupRenderer,
            $groupSegmentRenderer,
        );
        $this->pendingRenderState = new TablePendingRenderState();
        $this->sections = new TableSections();
        $this->tableStructElem = $page->getDocument()->getProfile()->requiresTaggedPdf()
            ? $page->getDocument()->createStructElem(StructureTag::Table)
            : null;
        $this->style = new TableStyle(
            padding: TablePadding::all(6.0),
            border: TableBorder::all(color: Color::gray(0.75)),
            verticalAlign: VerticalAlign::TOP,
        );
        $this->headerStyle = new HeaderStyle(fillColor: Color::gray(0.92));
        $page->getDocument()->registerDeferredRenderFinalizer($this->finalize(...));
    }

    public function font(string $baseFont, int $size): self
    {
        if ($baseFont === '') {
            throw new InvalidArgumentException('Table base font must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Table font size must be greater than zero.');
        }

        $this->baseFont = $baseFont;
        $this->fontSize = $size;

        return $this;
    }

    public function style(TableStyle $style): self
    {
        $this->style = $this->styleResolver->mergeTableStyle($this->style, $style);

        return $this;
    }

    public function rowStyle(RowStyle $style): self
    {
        $this->rowStyle = $this->styleResolver->mergeRowStyle($this->rowStyle, $style);

        return $this;
    }

    public function headerStyle(HeaderStyle $style): self
    {
        $this->headerStyle = $this->styleResolver->mergeHeaderStyle($this->headerStyle, $style);

        return $this;
    }

    public function footerStyle(FooterStyle $style): self
    {
        $this->footerStyle = $this->styleResolver->mergeFooterStyle($this->footerStyle, $style);

        return $this;
    }

    public function caption(TableCaption $caption): self
    {
        if (!$this->sections->canConfigureCaption()) {
            throw new InvalidArgumentException('Table caption must be configured before rows are added.');
        }

        $this->caption = $caption;

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addRow(array $cells): self
    {
        $this->sections->markBodyRowsAdded();

        return $this->addTypedRow($cells, false);
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addHeaderRow(array $cells, bool $repeat = true): self
    {
        if (!$this->sections->canAddHeaderRows()) {
            throw new InvalidArgumentException('Header rows must be added before body or footer rows.');
        }

        if ($repeat) {
            $this->sections->addRepeatingHeaderRow($cells);
        }

        return $this->addTypedRow($cells, true);
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addFooterRow(array $cells): self
    {
        $this->sections->addFooterRow($cells);

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    private function addTypedRow(array $cells, bool $header): self
    {
        $this->sections->markRowsAdded();

        $preparedRow = $this->prepareRow($cells, $header);
        $this->pendingRenderState->addRow(new PreparedTableRow($preparedRow['cells'], $header));
        $this->activeRowspans = $preparedRow['nextRowspans'];

        if (!$this->hasActiveRowspans()) {
            $this->flushPendingGroup();
        }

        return $this;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getCursorY(): float
    {
        return $this->cursorY;
    }

    private function flushPendingGroup(): void
    {
        $pendingGroupRows = $this->pendingRenderState->rows();
        $rowHeights = $this->rowGroupHeightResolver->resolve($pendingGroupRows);
        $pendingGroup = new PreparedTableRowGroup($pendingGroupRows, $rowHeights);
        $this->renderCaptionIfNeeded($rowHeights);
        $isBodyGroup = array_any(
            $pendingGroupRows,
            static fn (PreparedTableRow $row): bool => $row->header === false,
        );
        $repeatHeaders = $isBodyGroup && $this->sections->hasRepeatingHeaderRows();
        $repeatingHeaderGroup = $repeatHeaders ? $this->prepareRepeatingHeaderGroup() : null;
        $result = $this->pendingGroupFlow->render(
            $this->page,
            $this->cursorY,
            $pendingGroup,
            $repeatingHeaderGroup,
            $this->pendingRenderState,
            $this->renderContext(),
            $this->bottomMargin,
            $this->continuationTopMargin,
        );
        $this->page = $result->page;
        $this->cursorY = $result->cursorY;

        $this->pendingRenderState->clear();
    }

    private function prepareRepeatingHeaderGroup(): PreparedTableRowGroup
    {
        $preparedHeaderRows = $this->createRowGroupPreparer()->prepareGroup(
            $this->sections->repeatingHeaderRows(),
            true,
            false,
            'Header rowspans must be completed within the repeated header rows.',
        );

        return new PreparedTableRowGroup(
            $preparedHeaderRows,
            $this->rowGroupHeightResolver->resolve($preparedHeaderRows),
        );
    }

    private function hasActiveRowspans(): bool
    {
        return array_any(
            $this->activeRowspans,
            static fn (int $remainingRows): bool => $remainingRows > 0,
        );
    }

    private function finalize(): void
    {
        if ($this->sections->areFootersRendered() || !$this->sections->hasFooterRows()) {
            return;
        }

        if ($this->hasActiveRowspans()) {
            throw new InvalidArgumentException('Rowspan groups must be completed before footer rows are rendered.');
        }

        $preparedFooterRows = $this->createRowGroupPreparer()->prepareGroup(
            $this->sections->footerRows(),
            false,
            true,
            'Footer rowspans must be completed within the footer rows.',
        );
        $footerHeights = $this->rowGroupHeightResolver->resolve($preparedFooterRows);
        $result = $this->footerRenderer->render(
            $this->page,
            $this->cursorY,
            new PreparedTableRowGroup($preparedFooterRows, $footerHeights),
            $this->bottomMargin,
            $this->continuationTopMargin,
            $this->renderContext(),
        );
        $this->page = $result->page;
        $this->cursorY = $result->cursorY;
        $this->sections->markFootersRendered();
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     * @return array{cells: list<PreparedTableCell>, nextRowspans: list<int>}
     */
    private function prepareRow(array $cells, bool $header, bool $footer = false): array
    {
        return $this->createRowPreparer()->prepareRow($cells, $this->activeRowspans, $header, $footer);
    }

    private function createRowPreparer(): RowPreparer
    {
        return new RowPreparer(
            $this->page,
            $this->columnWidths,
            $this->baseFont,
            $this->fontSize,
            $this->lineHeightFactor,
            $this->style,
            $this->rowStyle,
            $this->headerStyle,
            $this->styleResolver,
            $this->textMetrics,
            $this->footerStyle,
        );
    }

    private function createRowGroupPreparer(): RowGroupPreparer
    {
        return new RowGroupPreparer($this->createRowPreparer(), count($this->columnWidths));
    }

    /**
     * @param list<float> $rowHeights
     */
    private function renderCaptionIfNeeded(array $rowHeights): void
    {
        $caption = $this->caption;

        if ($caption === null || $this->sections->isCaptionRendered()) {
            return;
        }

        $result = $this->captionRenderer->render(
            $caption,
            $this->page,
            $this->cursorY,
            $this->topMargin,
            $this->bottomMargin,
            $this->x,
            $this->width,
            $rowHeights[0] ?? 0.0,
            $this->baseFont,
            $this->fontSize,
            $this->lineHeightFactor,
            $this->tableStructElem,
        );
        $this->page = $result->page;
        $this->cursorY = $result->cursorY;
        $this->sections->markCaptionRendered();
    }

    private function renderContext(): TableRenderContext
    {
        return new TableRenderContext(
            $this->preparedCellRenderer,
            $this->style,
            $this->rowStyle,
            $this->headerStyle,
            $this->footerStyle,
            $this->baseFont,
            $this->fontSize,
            $this->lineHeightFactor,
            $this->tableStructElem,
        );
    }
}
