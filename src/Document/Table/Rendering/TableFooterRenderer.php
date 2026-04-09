<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use InvalidArgumentException;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Document\Table\Style\FooterStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Renders prepared table footer rows and resolves a required fresh-page move.
 */
final class TableFooterRenderer
{
    public function __construct(
        private readonly TableGroupRenderer $groupRenderer = new TableGroupRenderer(),
    ) {
    }

    public function render(
        Page $page,
        float $cursorY,
        PreparedTableRowGroup $footerGroup,
        float $bottomMargin,
        float $continuationTopMargin,
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
        $footerHeight = array_sum($footerGroup->rowHeights);
        $availableHeight = $cursorY - $bottomMargin;

        if ($footerHeight > $availableHeight) {
            $page = $page->getDocument()->addPage($page->getWidth(), $page->getHeight());
            $cursorY = $page->getHeight() - $continuationTopMargin;

            if ($footerHeight > ($cursorY - $bottomMargin)) {
                throw new InvalidArgumentException('Table footer rows must fit on a fresh page.');
            }
        }

        return $this->groupRenderer->render(
            $page,
            $footerGroup,
            $cursorY,
            $preparedCellRenderer,
            $style,
            $rowStyle,
            $headerStyle,
            $footerStyle,
            $baseFont,
            $fontSize,
            $lineHeightFactor,
            $tableStructElem,
        );
    }
}
