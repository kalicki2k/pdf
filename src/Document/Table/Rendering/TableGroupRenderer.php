<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRowGroup;
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

    public function render(
        Page $page,
        PreparedTableRowGroup $rowGroup,
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

        foreach ($rowGroup->rows as $rowIndex => $preparedRow) {
            $rowStructElem = $this->structElemFactory->createRow($page, $tableStructElem);

            foreach ($preparedRow->cells as $preparedCell) {
                $page = $preparedCellRenderer->render(
                    $page,
                    $preparedCell,
                    $preparedRow->header,
                    $rowIndex,
                    $rowGroup->rowHeights,
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

            $rowTopY -= $rowGroup->rowHeights[$rowIndex];
        }

        return new TableGroupRenderResult($page, $rowTopY);
    }
}
