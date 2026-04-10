<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Rendering;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Internal\Page\Page;

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
        TableRenderContext $context,
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
            $context,
        );
    }
}
