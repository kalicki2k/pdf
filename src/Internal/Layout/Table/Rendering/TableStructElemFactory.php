<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Rendering;

use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableHeaderScope;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;

/**
 * @internal Creates tagged PDF structure elements for rendered table rows and cells.
 */
final class TableStructElemFactory
{
    public function createRow(Page $page, ?StructElem $tableStructElem): ?StructElem
    {
        if ($tableStructElem === null) {
            return null;
        }

        return $page->getDocument()->createStructElem(StructureTag::TableRow, parent: $tableStructElem);
    }

    public function createCell(Page $page, TableCell $cell, bool $header, ?StructElem $rowStructElem): ?StructElem
    {
        if ($rowStructElem === null) {
            return null;
        }

        $headerScope = $this->resolveHeaderScope($cell, $header);

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

    private function resolveHeaderScope(TableCell $cell, bool $header): ?TableHeaderScope
    {
        if ($cell->headerScope !== null) {
            return $cell->headerScope;
        }

        return $header ? TableHeaderScope::Column : null;
    }
}
