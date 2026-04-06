<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Table\Layout\PreparedTableCellLayout;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PreparedTableCellLayoutTest extends TestCase
{
    #[Test]
    public function it_stores_prepared_table_cell_layout_values(): void
    {
        $layout = new PreparedTableCellLayout(10, 20, 30, 40, 12, 34, 26, 18);

        self::assertSame(10.0, $layout->x);
        self::assertSame(20.0, $layout->bottomY);
        self::assertSame(30.0, $layout->width);
        self::assertSame(40.0, $layout->height);
        self::assertSame(12.0, $layout->textX);
        self::assertSame(34.0, $layout->textY);
        self::assertSame(26.0, $layout->textWidth);
        self::assertSame(18.0, $layout->bottomLimitY);
    }
}
