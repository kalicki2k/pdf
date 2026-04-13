<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout\Table;

use function array_sum;

use InvalidArgumentException;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Document\TextFlow;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\TableLayoutCalculator;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class TableLayoutCalculatorTest extends TestCase
{
    public function testItResolvesMixedFixedAndProportionalColumnWidths(): void
    {
        $table = Table::define(
            TableColumn::fixed(60.0),
            TableColumn::proportional(1.0),
            TableColumn::proportional(3.0),
        );

        $widths = new TableLayoutCalculator()->resolveColumnWidths($table, 200.0);

        self::assertEquals([60.0, 35.0, 105.0], $widths);
    }

    public function testItResolvesAutoColumnWidthsFromCellContent(): void
    {
        $table = Table::define(
            TableColumn::auto(),
            TableColumn::proportional(1.0),
            TableColumn::auto(),
        )
            ->withOptions(
                (new TableOptions())
                    ->withCellPadding(CellPadding::symmetric(4.0, 6.0))
                    ->withTextOptions(new TextOptions(fontSize: 10.0, lineHeight: 12.0)),
            )
            ->withRows(
                TableRow::fromTexts('2026-03-31', 'Managed operations and release support', 'INC-4421'),
            );
        $page = new Page(PageSize::A4());
        $textFlow = new TextFlow($page);
        $font = StandardFontDefinition::from('Helvetica');

        $widths = new TableLayoutCalculator()->resolveColumnWidths($table, 220.0, $textFlow, $font);

        self::assertGreaterThan(45.0, $widths[0]);
        self::assertGreaterThan(40.0, $widths[2]);
        self::assertEqualsWithDelta(220.0, array_sum($widths), 0.001);
        self::assertGreaterThan($widths[0], $widths[1]);
    }

    public function testItRejectsAutoColumnsWithoutMeasurementContext(): void
    {
        $table = Table::define(
            TableColumn::auto(),
            TableColumn::proportional(1.0),
        );

        $this->expectExceptionObject(new InvalidArgumentException(
            'Auto table columns require a text flow and font for width resolution.',
        ));

        new TableLayoutCalculator()->resolveColumnWidths($table, 120.0);
    }

    public function testItUsesTheTallestCellForRowHeight(): void
    {
        $table = Table::define(
            TableColumn::fixed(50.0),
            TableColumn::fixed(50.0),
        )
            ->withOptions(
                (new TableOptions())
                    ->withCellPadding(CellPadding::all(5.0))
                    ->withTextOptions(new TextOptions(fontSize: 10.0, lineHeight: 12.0)),
            )
            ->withRows(TableRow::fromTexts('Alpha Beta Gamma', 'Short'));
        $calculator = new TableLayoutCalculator();
        $font = StandardFontDefinition::from('Helvetica');
        $page = new Page(PageSize::A4());
        $textFlow = new TextFlow($page);
        $layout = $calculator->layoutTable($table, [50.0, 50.0], $textFlow, $font);
        $leftCell = $layout->cells[0];
        $rightCell = $layout->cells[1];

        self::assertGreaterThan(1, count($leftCell->wrappedLines));
        self::assertSame(22.0, $rightCell->height);
        self::assertSame($layout->cellHeight($leftCell), $layout->rowHeights[0]);
        self::assertGreaterThan($rightCell->height, $layout->rowHeights[0]);
    }

    public function testItExpandsAColspanCellToTheCombinedColumnWidth(): void
    {
        $table = Table::define(
            TableColumn::fixed(40.0),
            TableColumn::fixed(60.0),
            TableColumn::fixed(50.0),
        )->withRows(
            TableRow::fromCells(
                TableCell::text('Wide', colspan: 2),
                TableCell::text('Tail'),
            ),
        );

        $layout = new TableLayoutCalculator()->layoutTable(
            $table,
            [40.0, 60.0, 50.0],
            new TextFlow(new Page(PageSize::A4())),
            StandardFontDefinition::from('Helvetica'),
        );

        self::assertSame(100.0, $layout->cells[0]->width);
        self::assertSame(92.0, $layout->cells[0]->contentWidth);
        self::assertSame(2, $layout->cells[0]->cell->colspan);
        self::assertSame(2, $layout->cells[1]->columnIndex);
    }

    public function testItGrowsTheLastRowOfARowspanToFitTheSpannedCell(): void
    {
        $table = Table::define(
            TableColumn::fixed(55.0),
            TableColumn::fixed(55.0),
        )
            ->withOptions(
                (new TableOptions())
                    ->withCellPadding(CellPadding::all(5.0))
                    ->withTextOptions(new TextOptions(fontSize: 10.0, lineHeight: 12.0)),
            )
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('Alpha Beta Gamma Delta Epsilon', rowspan: 2),
                    TableCell::text('Short'),
                ),
                TableRow::fromCells(
                    TableCell::text('Tail'),
                ),
            );

        $layout = new TableLayoutCalculator()->layoutTable(
            $table,
            [55.0, 55.0],
            new TextFlow(new Page(PageSize::A4())),
            StandardFontDefinition::from('Helvetica'),
        );

        self::assertSame(2, $layout->cells[0]->cell->rowspan);
        self::assertGreaterThan(22.0, $layout->rowHeights[1]);
        self::assertSame($layout->cells[0]->height, $layout->cellHeight($layout->cells[0]));
        self::assertSame(1, count($layout->rowGroups));
    }

    public function testItUsesCellSpecificPaddingBorderAndHorizontalAlignmentDuringLayout(): void
    {
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )
            ->withOptions(
                (new TableOptions())
                    ->withCellPadding(CellPadding::all(4.0))
                    ->withBorder(Border::all(0.5))
                    ->withPlacement(new TablePlacement(40.0, 160.0))
                    ->withTextOptions(new TextOptions(fontSize: 10.0, lineHeight: 12.0)),
            )
            ->withRows(TableRow::fromCells(
                TableCell::text('Alpha')
                    ->withPadding(CellPadding::symmetric(8.0, 10.0))
                    ->withBorder(new Border(1.0, 2.0, 3.0, 4.0))
                    ->withHorizontalAlign(TextAlign::CENTER),
                TableCell::text('Beta'),
            ));

        $layout = new TableLayoutCalculator()->layoutTable(
            $table,
            [80.0, 80.0],
            new TextFlow(new Page(PageSize::A4())),
            StandardFontDefinition::from('Helvetica'),
        );

        self::assertSame(60.0, $layout->cells[0]->contentWidth);
        self::assertSame(28.0, $layout->cells[0]->height);
        self::assertEquals(CellPadding::symmetric(8.0, 10.0), $layout->cells[0]->padding);
        self::assertEquals(new Border(1.0, 2.0, 3.0, 4.0), $layout->cells[0]->border);
        self::assertSame(TextAlign::CENTER, $layout->cells[0]->textOptions->align);
        self::assertEquals(CellPadding::all(4.0), $layout->cells[1]->padding);
    }

    public function testItLayoutsRichTextCellsWithWrappedSegmentLines(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
        )
            ->withOptions(
                (new TableOptions())
                    ->withCellPadding(CellPadding::all(5.0))
                    ->withTextOptions(new TextOptions(fontSize: 10.0, lineHeight: 12.0)),
            )
            ->withRows(TableRow::fromCells(
                TableCell::segments(
                    TextSegment::plain('Read the '),
                    TextSegment::link('documentation', TextLink::externalUrl('https://example.com/docs')),
                    TextSegment::plain(' carefully before rollout.'),
                ),
            ));

        $layout = new TableLayoutCalculator()->layoutTable(
            $table,
            [90.0],
            new TextFlow(new Page(PageSize::A4())),
            StandardFontDefinition::from('Helvetica'),
        );

        self::assertTrue($layout->cells[0]->usesRichText());
        self::assertNotNull($layout->cells[0]->wrappedSegmentLines);
        self::assertGreaterThan(1, $layout->cells[0]->lineCount());
        self::assertSame($layout->cells[0]->lineCount(), count($layout->cells[0]->wrappedSegmentLines));
    }
}
