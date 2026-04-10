<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Table\Definition;

use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Style\CellStyle;
use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableCellTest extends TestCase
{
    #[Test]
    public function it_stores_table_cell_values(): void
    {
        $style = new CellStyle(horizontalAlign: HorizontalAlign::CENTER);
        $segments = [new TextSegment('A'), new TextSegment('B', underline: true)];

        $cell = new TableCell($segments, colspan: 2, rowspan: 3, style: $style);

        self::assertSame($segments, $cell->text);
        self::assertSame(2, $cell->colspan);
        self::assertSame(3, $cell->rowspan);
        self::assertSame($style, $cell->style);
    }
}
