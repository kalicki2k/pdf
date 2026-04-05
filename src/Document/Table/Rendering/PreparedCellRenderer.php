<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Document\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\Support\TableStyleResolver;

final readonly class PreparedCellRenderer
{
    public function __construct(
        private TableStyleResolver $styleResolver,
        private CellLayoutResolver $cellLayoutResolver,
        private CellBoxRenderer $cellBoxRenderer,
    ) {
    }

    /**
     * @param list<float> $rowHeights
     */
    public function render(
        Page $page,
        PreparedTableCell $preparedCell,
        bool $header,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
        TableStyle $tableStyle,
        ?RowStyle $rowStyle,
        ?HeaderStyle $headerStyle,
        string $baseFont,
        int $fontSize,
    ): Page {
        $resolvedStyle = $this->styleResolver->resolveCellStyle(
            $tableStyle,
            $rowStyle,
            $headerStyle,
            $preparedCell->cell,
            $header,
        );

        $layout = $this->cellLayoutResolver->resolve(
            $preparedCell,
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $resolvedStyle->verticalAlign,
            $fontSize,
        );

        $this->cellBoxRenderer->render(
            $page,
            $layout->x,
            $layout->bottomY,
            $layout->width,
            $layout->height,
            $resolvedStyle->fillColor,
            $tableStyle->border,
            $resolvedStyle->rowBorder,
            $resolvedStyle->cellBorder,
        );

        return $page->addParagraph(
            $preparedCell->cell->text,
            $layout->textX,
            $layout->textY,
            $layout->textWidth,
            $baseFont,
            $fontSize,
            null,
            $lineHeight,
            $layout->bottomLimitY,
            $resolvedStyle->textColor,
            $resolvedStyle->opacity,
            $resolvedStyle->horizontalAlign,
        );
    }
}
