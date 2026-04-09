<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Feature\Table\PendingRowspanCell;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Feature\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Feature\Table\TableCell;
use Kalle\Pdf\Feature\Text\TextSegment;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PendingRowspanCellTest extends TestCase
{
    #[Test]
    public function it_stores_pending_rowspan_cell_values(): void
    {
        $padding = TablePadding::only(top: 1, right: 2, bottom: 3, left: 4);
        $cell = new PreparedTableCell(
            new TableCell('Value', rowspan: 2),
            120,
            1,
            18,
            24,
            30,
            $padding,
        );
        $style = new ResolvedTableCellStyle(
            $padding,
            Color::gray(0.9),
            Color::rgb(255, 0, 0),
            VerticalAlign::MIDDLE,
            HorizontalAlign::CENTER,
            Opacity::both(0.4),
            null,
            null,
        );
        $remainingLines = [
            [
                'segments' => [new TextSegment('continued')],
                'justify' => false,
            ],
        ];

        $pendingCell = new PendingRowspanCell($cell, $style, 2, $remainingLines);

        self::assertSame($cell, $pendingCell->cell);
        self::assertSame($style, $pendingCell->style);
        self::assertSame(2, $pendingCell->remainingRows);
        self::assertSame($remainingLines, $pendingCell->remainingLines);
    }
}
