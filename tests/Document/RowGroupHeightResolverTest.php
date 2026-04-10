<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Internal\Layout\Table\Layout\RowGroupHeightResolver;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RowGroupHeightResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_row_heights_for_simple_rows(): void
    {
        $resolver = new RowGroupHeightResolver();

        $heights = $resolver->resolve([
            new PreparedTableRow([
                $this->createPreparedCell('A', 10),
                $this->createPreparedCell('B', 14),
            ], false),
            new PreparedTableRow([
                $this->createPreparedCell('C', 12),
            ], false),
        ]);

        self::assertSame([14.0, 12.0], $heights);
    }

    #[Test]
    public function it_adds_missing_height_to_the_last_row_of_a_rowspan_group(): void
    {
        $resolver = new RowGroupHeightResolver();

        $heights = $resolver->resolve([
            new PreparedTableRow([
                $this->createPreparedCell('A', 30, rowspan: 2),
                $this->createPreparedCell('B', 12),
            ], false),
            new PreparedTableRow([
                $this->createPreparedCell('C', 10),
            ], false),
        ]);

        self::assertSame([12.0, 18.0], $heights);
    }

    #[Test]
    public function it_rejects_unfinished_rowspan_groups(): void
    {
        $resolver = new RowGroupHeightResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rowspan groups must be completed by subsequent rows.');

        $resolver->resolve([
            new PreparedTableRow([
                $this->createPreparedCell('A', 20, rowspan: 2),
            ], false),
        ]);
    }

    private function createPreparedCell(string $text, float $minHeight, int $rowspan = 1): PreparedTableCell
    {
        return new PreparedTableCell(
            new TableCell($text, rowspan: $rowspan),
            100,
            0,
            $minHeight,
            $minHeight,
            $minHeight,
            TablePadding::all(0),
        );
    }
}
