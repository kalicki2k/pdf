<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Layout\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Table\Style\TablePadding;
use Kalle\Pdf\Table\TableCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CellLayoutResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_a_top_aligned_cell_layout(): void
    {
        $resolver = new CellLayoutResolver(10, [50, 60, 70]);
        $preparedCell = new PreparedTableCell(
            new TableCell('Value'),
            60,
            1,
            18,
            14,
            16,
            TablePadding::only(top: 2, right: 3, bottom: 4, left: 5),
        );

        $layout = $resolver->resolve($preparedCell, 1, [20.0, 30.0, 40.0], 100, VerticalAlign::TOP, 12);

        self::assertSame(60.0, $layout->x);
        self::assertSame(70.0, $layout->bottomY);
        self::assertSame(60.0, $layout->width);
        self::assertSame(30.0, $layout->height);
        self::assertSame(65.0, $layout->textX);
        self::assertSame(86.0, $layout->textY);
        self::assertSame(52.0, $layout->textWidth);
        self::assertSame(73.99, $layout->bottomLimitY);
    }

    #[Test]
    public function it_resolves_middle_and_bottom_aligned_layouts_with_visible_rowspan(): void
    {
        $resolver = new CellLayoutResolver(10, [50, 60, 70]);
        $preparedCell = new PreparedTableCell(
            new TableCell('Value', rowspan: 3),
            60,
            1,
            18,
            14,
            16,
            TablePadding::only(top: 2, right: 3, bottom: 4, left: 5),
        );

        $middleLayout = $resolver->resolve($preparedCell, 0, [20.0, 30.0, 40.0], 100, VerticalAlign::MIDDLE, 12, 2);
        $bottomLayout = $resolver->resolve($preparedCell, 0, [20.0, 30.0, 40.0], 100, VerticalAlign::BOTTOM, 12, 2);

        self::assertSame(72.0, $middleLayout->textY);
        self::assertSame(58.0, $bottomLayout->textY);
        self::assertSame(50.0, $middleLayout->height);
        self::assertSame(50.0, $bottomLayout->height);
        self::assertSame(53.99, $middleLayout->bottomLimitY);
    }
}
