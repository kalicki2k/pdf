<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Document\Table\Style\FooterStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\Table\TableHeaderScope;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Renders prepared table row groups without owning page-flow decisions.
 */
final class TableGroupRenderer
{
    /**
     * @param list<PreparedTableRow> $preparedRows
     * @param list<float> $rowHeights
     */
    public function render(
        Page $page,
        array $preparedRows,
        array $rowHeights,
        float $cursorY,
        PreparedCellRenderer $preparedCellRenderer,
        TableStyle $style,
        ?RowStyle $rowStyle,
        ?HeaderStyle $headerStyle,
        ?FooterStyle $footerStyle,
        string $baseFont,
        int $fontSize,
        float $lineHeightFactor,
        ?StructElem $tableStructElem,
    ): TableGroupRenderResult {
        $lineHeight = $fontSize * $lineHeightFactor;
        $rowTopY = $cursorY;

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            $rowStructElem = $this->createTableRowStructElem($page, $tableStructElem);

            foreach ($preparedRow->cells as $preparedCell) {
                $page = $preparedCellRenderer->render(
                    $page,
                    $preparedCell,
                    $preparedRow->header,
                    $rowIndex,
                    $rowHeights,
                    $rowTopY,
                    $lineHeight,
                    $style,
                    $rowStyle,
                    $headerStyle,
                    $baseFont,
                    $fontSize,
                    $this->createTableCellStructElem($page, $preparedCell->cell, $preparedRow->header, $rowStructElem),
                    $footerStyle,
                    $preparedRow->footer,
                );
            }

            $rowTopY -= $rowHeights[$rowIndex];
        }

        return new TableGroupRenderResult($page, $rowTopY);
    }

    private function createTableRowStructElem(Page $page, ?StructElem $tableStructElem): ?StructElem
    {
        if ($tableStructElem === null) {
            return null;
        }

        return $page->getDocument()->createStructElem(StructureTag::TableRow, parent: $tableStructElem);
    }

    private function createTableCellStructElem(
        Page $page,
        TableCell $cell,
        bool $header,
        ?StructElem $rowStructElem,
    ): ?StructElem {
        if ($rowStructElem === null) {
            return null;
        }

        $headerScope = $this->resolveTableCellHeaderScope($cell, $header);

        $structElem = $page->getDocument()->createStructElem(
            $headerScope === null ? StructureTag::TableDataCell : StructureTag::TableHeaderCell,
            parent: $rowStructElem,
        );

        if ($headerScope !== null) {
            $structElem->setScope($headerScope->value);
        }

        if ($cell->rowspan > 1) {
            $structElem->setRowSpan($cell->rowspan);
        }

        if ($cell->colspan > 1) {
            $structElem->setColSpan($cell->colspan);
        }

        return $structElem;
    }

    private function resolveTableCellHeaderScope(TableCell $cell, bool $header): ?TableHeaderScope
    {
        if ($cell->headerScope !== null) {
            return $cell->headerScope;
        }

        return $header ? TableHeaderScope::Column : null;
    }
}
