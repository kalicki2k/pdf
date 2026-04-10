<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Table\Table as LayoutTable;
use Kalle\Pdf\Layout\Text\TextFrame as LayoutTextFrame;
use Kalle\Pdf\Table\Table;
use Kalle\Pdf\Text\TextFrame;

trait HandlesPageBuilders
{
    private const float DEFAULT_BUILDER_BOTTOM_MARGIN = 20.0;

    public function createTextFrame(
        Position $position,
        float $width,
        float $bottomMargin = self::DEFAULT_BUILDER_BOTTOM_MARGIN,
    ): TextFrame {
        return new TextFrame(new LayoutTextFrame($this, $position->x, $position->y, $width, $bottomMargin));
    }

    /**
     * @param list<float|int> $columnWidths
     */
    public function createTable(
        Position $position,
        float $width,
        array $columnWidths,
        float $bottomMargin = self::DEFAULT_BUILDER_BOTTOM_MARGIN,
    ): Table {
        return new Table(new LayoutTable($this, $position->x, $position->y, $width, $columnWidths, $bottomMargin));
    }
}
