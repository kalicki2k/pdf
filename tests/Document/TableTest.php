<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableCellContent;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    public function testItRejectsRowsWithAMismatchedCellCount(): void
    {
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        );

        $this->expectException(InvalidArgumentException::class);

        $table->addRow(TableRow::fromTexts('Only one cell'));
    }

    public function testItAcceptsCellsWithColspanWhenTheyCoverTheGrid(): void
    {
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )->withRows(
            TableRow::fromCells(
                TableCell::text('Wide', colspan: 2),
                TableCell::text('Tail'),
            ),
        );

        self::assertCount(1, $table->rows);
    }

    public function testItRejectsRowsThatDoNotFullyCoverTheGridWithActiveRowspans(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )->withRows(
            TableRow::fromCells(
                TableCell::text('A', rowspan: 2),
                TableCell::text('B'),
            ),
            TableRow::fromCells(
                TableCell::text('Missing second grid slot'),
            ),
        );
    }

    public function testItStoresHeaderRowsAndRepeatFlagExplicitly(): void
    {
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )
            ->withHeaderRows(TableRow::fromTexts('H1', 'H2'))
            ->withRows(TableRow::fromTexts('A', 'B'))
            ->withRepeatedHeaderOnPageBreak();

        self::assertCount(1, $table->headerRows);
        self::assertTrue($table->repeatHeaderOnPageBreak);
    }

    public function testItStoresCaptionAndFooterRowsExplicitly(): void
    {
        $caption = TableCaption::text('Quarterly overview')->withSpacingAfter(8.0);
        $placement = new TablePlacement(48.0, 220.0);
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )
            ->withCaption($caption)
            ->withPlacement($placement)
            ->withRows(TableRow::fromTexts('A', 'B'))
            ->withFooterRows(TableRow::fromTexts('Total', '2'));

        self::assertSame($caption, $table->caption);
        self::assertSame($placement, $table->placement);
        self::assertCount(1, $table->footerRows);
    }

    public function testItStoresExplicitHeaderScopeOnCells(): void
    {
        $cell = TableCell::text('North')->withHeaderScope(TableHeaderScope::ROW);

        self::assertSame(TableHeaderScope::ROW, $cell->headerScope);
    }

    public function testItStoresAbsolutePlacementAndRichCellContentExplicitly(): void
    {
        $placement = TablePlacement::at(48.0, 460.0, 220.0);
        $cell = TableCell::segments(
            TextSegment::plain('Read '),
            TextSegment::link('docs', TextLink::externalUrl('https://example.com/docs')),
        );
        $table = Table::define(
            TableColumn::fixed(80.0),
        )
            ->withPlacement($placement)
            ->withRows(TableRow::fromCells($cell));

        self::assertSame($placement, $table->placement);
        self::assertTrue($table->rows[0]->cells[0]->content->isRichText());
        self::assertSame('Read docs', $table->rows[0]->cells[0]->text);
        self::assertEquals(TableCellContent::segments(
            TextSegment::plain('Read '),
            TextSegment::link('docs', TextLink::externalUrl('https://example.com/docs')),
        ), $table->rows[0]->cells[0]->content);
    }
}
