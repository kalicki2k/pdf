<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Document\Table\Style\FooterStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Renders prepared table row groups without owning page-flow decisions.
 */
final class TableGroupRenderer
{
    public function __construct(
        private readonly TableStructElemFactory $structElemFactory = new TableStructElemFactory(),
    ) {
    }

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
            $rowStructElem = $this->structElemFactory->createRow($page, $tableStructElem);

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
                    $this->structElemFactory->createCell($page, $preparedCell->cell, $preparedRow->header, $rowStructElem),
                    $footerStyle,
                    $preparedRow->footer,
                );
            }

            $rowTopY -= $rowHeights[$rowIndex];
        }

        return new TableGroupRenderResult($page, $rowTopY);
    }
}
