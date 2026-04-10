<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Rendering;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Internal\Layout\Table\TableGroupPageFit;
use Kalle\Pdf\Internal\Page\Page;

/**
 * @internal Owns the page-flow loop for rendering pending prepared table row groups.
 */
final class TablePendingGroupFlow
{
    public function __construct(
        private readonly TablePendingGroupPaginator $pendingGroupPaginator,
        private readonly TableGroupRenderer $groupRenderer,
        private readonly TableGroupSegmentRenderer $groupSegmentRenderer,
    ) {
    }

    public function render(
        Page $page,
        float $cursorY,
        PreparedTableRowGroup $pendingGroup,
        ?PreparedTableRowGroup $repeatingHeaderGroup,
        TablePendingRenderState $pendingRenderState,
        TableRenderContext $context,
        float $bottomMargin,
        float $continuationTopMargin,
    ): TablePendingGroupFlowResult {
        $remainingGroup = $pendingGroup;
        $deferredLeadingSplit = false;
        $repeatHeaders = $repeatingHeaderGroup !== null;

        while (!$remainingGroup->isEmpty()) {
            $pageFit = $this->resolvePageFit($remainingGroup, $cursorY, $bottomMargin, $repeatHeaders);
            $fittingRowCount = $pageFit->fittingRowCountOnCurrentPage;

            if ($fittingRowCount === 0) {
                $nextPage = $this->moveToNextPage($page, $continuationTopMargin, $repeatingHeaderGroup, $context, $pageFit->repeatHeaders);
                $page = $nextPage->page;
                $cursorY = $nextPage->cursorY;
                $pageFit = $this->resolvePageFit($remainingGroup, $cursorY, $bottomMargin, false);
                $fittingRowCount = $pageFit->fittingRowCountOnCurrentPage;

                if ($fittingRowCount === 0) {
                    throw new InvalidArgumentException('Table rows must fit on a fresh page.');
                }
            }

            if (
                !$deferredLeadingSplit
                && $this->pendingGroupPaginator->shouldDeferLeadingSplit(
                    $remainingGroup->rows,
                    $pendingRenderState->hasPendingRowspanCells(),
                    $fittingRowCount,
                )
            ) {
                $nextPage = $this->moveToNextPage($page, $continuationTopMargin, $repeatingHeaderGroup, $context, $repeatHeaders);
                $page = $nextPage->page;
                $cursorY = $nextPage->cursorY;
                $deferredLeadingSplit = true;
                continue;
            }

            if ($fittingRowCount >= $remainingGroup->count() && !$pendingRenderState->hasPendingRowspanCells()) {
                $renderedGroup = $this->renderGroup($page, $remainingGroup, $cursorY, $context);
                $page = $renderedGroup->page;
                $cursorY = $renderedGroup->cursorY;

                break;
            }

            $segmentResult = $this->groupSegmentRenderer->render(
                $page,
                $remainingGroup->rows,
                $remainingGroup->rowHeights,
                $fittingRowCount,
                $cursorY,
                $pendingRenderState->pendingRowspanCells(),
                $context,
            );
            $page = $segmentResult->page;
            $cursorY = $segmentResult->cursorY;
            $pendingRenderState->replacePendingRowspanCells($segmentResult->pendingRowspanCells);

            if ($fittingRowCount >= $remainingGroup->count()) {
                break;
            }

            $remainingGroup = $remainingGroup->slice($fittingRowCount);
            $nextPage = $this->moveToNextPage($page, $continuationTopMargin, $repeatingHeaderGroup, $context, $repeatHeaders);
            $page = $nextPage->page;
            $cursorY = $nextPage->cursorY;
        }

        return new TablePendingGroupFlowResult($page, $cursorY);
    }

    private function resolvePageFit(
        PreparedTableRowGroup $rowGroup,
        float $cursorY,
        float $bottomMargin,
        bool $repeatHeaders,
    ): TableGroupPageFit {
        return $this->pendingGroupPaginator->resolvePageFit(
            $rowGroup->rowHeights,
            $cursorY - $bottomMargin,
            $repeatHeaders,
        );
    }

    private function moveToNextPage(
        Page $page,
        float $continuationTopMargin,
        ?PreparedTableRowGroup $repeatingHeaderGroup,
        TableRenderContext $context,
        bool $repeatHeaders,
    ): TablePendingGroupFlowResult {
        $page = $page->getDocument()->addPage($page->getWidth(), $page->getHeight());
        $cursorY = $page->getHeight() - $continuationTopMargin;

        if (!$repeatHeaders || $repeatingHeaderGroup === null) {
            return new TablePendingGroupFlowResult($page, $cursorY);
        }

        return $this->renderGroup($page, $repeatingHeaderGroup, $cursorY, $context);
    }

    private function renderGroup(
        Page $page,
        PreparedTableRowGroup $rowGroup,
        float $cursorY,
        TableRenderContext $context,
    ): TablePendingGroupFlowResult {
        $result = $this->groupRenderer->render($page, $rowGroup, $cursorY, $context);

        return new TablePendingGroupFlowResult($result->page, $result->cursorY);
    }
}
