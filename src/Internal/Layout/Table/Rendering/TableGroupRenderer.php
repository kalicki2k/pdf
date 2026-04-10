<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Rendering;

use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Page;

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
        TableRenderContext $context,
    ): TableGroupRenderResult {
        $lineHeight = $context->lineHeight();
        $rowTopY = $cursorY;

        foreach ($rowGroup->rows as $rowIndex => $preparedRow) {
            $rowStructElem = $this->structElemFactory->createRow($page, $context->tableStructElem);

            foreach ($preparedRow->cells as $preparedCell) {
                $page = $context->preparedCellRenderer->render(
                    $page,
                    $preparedCell,
                    $preparedRow->header,
                    $rowIndex,
                    $rowGroup->rowHeights,
                    $rowTopY,
                    $lineHeight,
                    $context->style,
                    $context->rowStyle,
                    $context->headerStyle,
                    $context->baseFont,
                    $context->fontSize,
                    $this->structElemFactory->createCell($page, $preparedCell->cell, $preparedRow->header, $rowStructElem),
                    $context->footerStyle,
                    $preparedRow->footer,
                );
            }

            $rowTopY -= $rowGroup->rowHeights[$rowIndex];
        }

        return new TableGroupRenderResult($page, $rowTopY);
    }
}
