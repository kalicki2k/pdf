<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\VerticalAlign;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;

use function number_format;

use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderTableTest extends TestCase
{
    public function testItRendersASimpleTableOnASinglePage(): void
    {
        $padding = CellPadding::all(6.0);
        $table = Table::define(
            TableColumn::fixed(100.0),
            TableColumn::proportional(1.0),
        )
            ->withCellPadding($padding)
            ->withBorder(Border::all(0.5))
            ->withTextOptions(new TextOptions(fontSize: 12.0, lineHeight: 14.4))
            ->withRows(
                TableRow::fromTexts('Name', 'Value'),
                TableRow::fromTexts('Alpha', 'Beta'),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->build();

        $page = $document->pages[0];
        $font = StandardFontDefinition::from('Helvetica');
        $contentArea = $page->contentArea();
        $firstRowTopY = $contentArea->top;
        $firstRowTextY = $firstRowTopY - $padding->top - $font->ascent(12.0);
        $firstColumnX = $contentArea->left + $padding->left;
        $secondColumnX = $contentArea->left + 100.0 + $padding->left;

        self::assertCount(1, $document->pages);
        self::assertStringContainsString("0.5 w\n" . $this->formatNumber($contentArea->left) . ' ' . $this->formatNumber($firstRowTopY) . ' m', $page->contents);
        self::assertStringContainsString(
            "BT\n/F1 12 Tf\n" . $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($firstRowTextY) . ' Td',
            $page->contents,
        );
        self::assertStringContainsString(
            "BT\n/F1 12 Tf\n" . $this->formatNumber($secondColumnX) . ' ' . $this->formatNumber($firstRowTextY) . ' Td',
            $page->contents,
        );
    }

    public function testItAdvancesTheFlowCursorAfterARenderedTable(): void
    {
        $table = Table::define(
            TableColumn::fixed(100.0),
            TableColumn::fixed(100.0),
        )->withRows(
            TableRow::fromTexts('One', 'Two'),
        );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->text('After table')
            ->build();
        $page = $document->pages[0];
        $tableBottomY = $page->contentArea()->top - (14.4 + 8.0);

        self::assertStringContainsString("BT\n/F1 12 Tf\n", $document->pages[0]->contents);
        self::assertStringContainsString(
            "BT\n/F1 18 Tf\n56.693 " . $this->formatNumber($tableBottomY) . " Td\n[",
            $document->pages[0]->contents,
        );
    }

    public function testItCreatesANewPageBeforeARowThatDoesNotFit(): void
    {
        $rows = [];

        for ($index = 1; $index <= 10; $index++) {
            $rows[] = TableRow::fromTexts('Item ' . $index, 'Value ' . $index);
        }

        $table = Table::define(
            TableColumn::proportional(1.0),
            TableColumn::proportional(1.0),
        )
            ->withCellPadding(CellPadding::all(6.0))
            ->withTextOptions(new TextOptions(fontSize: 12.0, lineHeight: 14.4))
            ->withRows(...$rows);
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();
        $font = StandardFontDefinition::from('Helvetica');
        $firstRowTextY = $document->pages[0]->contentArea()->top - 6.0 - $font->ascent(12.0);
        $firstColumnX = $document->pages[0]->contentArea()->left + 6.0;

        self::assertCount(2, $document->pages);
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($firstRowTextY) . ' Td',
            $document->pages[0]->contents,
        );
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($firstRowTextY) . ' Td',
            $document->pages[1]->contents,
        );
    }

    public function testItDoesNotRepeatHeaderRowsByDefault(): void
    {
        $rows = [];

        for ($index = 1; $index <= 10; $index++) {
            $rows[] = TableRow::fromTexts('Item ' . $index, 'Value ' . $index);
        }

        $table = Table::define(
            TableColumn::proportional(1.0),
            TableColumn::proportional(1.0),
        )
            ->withHeaderRows(TableRow::fromTexts('Head Left', 'Head Right'))
            ->withCellPadding(CellPadding::all(6.0))
            ->withTextOptions(new TextOptions(fontSize: 12.0, lineHeight: 14.4))
            ->withRows(...$rows);
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();
        $font = StandardFontDefinition::from('Helvetica');
        $firstColumnX = $document->pages[0]->contentArea()->left + 6.0;
        $secondHeaderLineY = $document->pages[0]->contentArea()->top - 6.0 - $font->ascent(12.0) - 14.4;

        self::assertCount(2, $document->pages);
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($secondHeaderLineY) . ' Td',
            $document->pages[0]->contents,
        );
        self::assertStringNotContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($secondHeaderLineY) . ' Td',
            $document->pages[1]->contents,
        );
    }

    public function testItRepeatsHeaderRowsOnFollowUpPagesWhenEnabled(): void
    {
        $rows = [];

        for ($index = 1; $index <= 10; $index++) {
            $rows[] = TableRow::fromTexts('Item ' . $index, 'Value ' . $index);
        }

        $table = Table::define(
            TableColumn::proportional(1.0),
            TableColumn::proportional(1.0),
        )
            ->withHeaderRows(TableRow::fromTexts('Head Left', 'Head Right'))
            ->withRepeatedHeaderOnPageBreak()
            ->withCellPadding(CellPadding::all(6.0))
            ->withTextOptions(new TextOptions(fontSize: 12.0, lineHeight: 14.4))
            ->withRows(...$rows);
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();
        $font = StandardFontDefinition::from('Helvetica');
        $firstColumnX = $document->pages[0]->contentArea()->left + 6.0;
        $secondHeaderLineY = $document->pages[0]->contentArea()->top - 6.0 - $font->ascent(12.0) - 14.4;

        self::assertCount(2, $document->pages);
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($secondHeaderLineY) . ' Td',
            $document->pages[0]->contents,
        );
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($secondHeaderLineY) . ' Td',
            $document->pages[1]->contents,
        );
    }

    public function testItRendersAColspanCellAcrossMultipleColumns(): void
    {
        $padding = CellPadding::all(6.0);
        $table = Table::define(
            TableColumn::fixed(60.0),
            TableColumn::fixed(70.0),
            TableColumn::fixed(80.0),
        )
            ->withCellPadding($padding)
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('Wide', colspan: 2),
                    TableCell::text('Right'),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->build();
        $contentArea = $document->pages[0]->contentArea();
        $font = StandardFontDefinition::from('Helvetica');
        $textY = $contentArea->top - 6.0 - $font->ascent(12.0);
        $rightCellX = $contentArea->left + 60.0 + 70.0 + 6.0;

        self::assertStringContainsString(
            '186.693 ' . $this->formatNumber($contentArea->top) . ' l',
            $document->pages[0]->contents,
        );
        self::assertStringContainsString(
            $this->formatNumber($rightCellX) . ' ' . $this->formatNumber($textY) . ' Td',
            $document->pages[0]->contents,
        );
    }

    public function testItRendersACellBackgroundBeforeItsText(): void
    {
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )->withRows(
            TableRow::fromCells(
                TableCell::text('Tinted')->withBackgroundColor(Color::rgb(1.0, 0.9, 0.8)),
                TableCell::text('Plain'),
            ),
        );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->build();
        $contents = $document->pages[0]->contents;
        $backgroundSnippet = "1 0.9 0.8 rg\n56.693 516.183 80 22.4 re\nf";
        $textSnippet = "BT\n/F1 12 Tf\n60.693 522.583 Td";

        self::assertStringContainsString($backgroundSnippet, $contents);
        self::assertStringContainsString($textSnippet, $contents);
        self::assertLessThan(strpos($contents, $textSnippet), strpos($contents, $backgroundSnippet));
    }

    public function testItRendersARowspanCellOnlyOnceAndShiftsTheNextRow(): void
    {
        $padding = CellPadding::all(6.0);
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )
            ->withCellPadding($padding)
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('Merged', rowspan: 2),
                    TableCell::text('Top'),
                ),
                TableRow::fromCells(
                    TableCell::text('Bottom'),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->build();
        $page = $document->pages[0];
        $contentArea = $page->contentArea();
        $font = StandardFontDefinition::from('Helvetica');
        $firstRowTextY = $contentArea->top - 6.0 - $font->ascent(12.0);
        $secondRowTopY = $contentArea->top - 26.4;
        $secondRowTextY = $secondRowTopY - 6.0 - $font->ascent(12.0);
        $secondColumnX = $contentArea->left + 80.0 + 6.0;

        self::assertSame(1, substr_count($page->contents, "BT\n/F1 12 Tf\n" . $this->formatNumber($contentArea->left + 6.0) . ' '));
        self::assertStringContainsString(
            $this->formatNumber($secondColumnX) . ' ' . $this->formatNumber($firstRowTextY) . ' Td',
            $page->contents,
        );
        self::assertStringContainsString(
            $this->formatNumber($secondColumnX) . ' ' . $this->formatNumber($secondRowTextY) . ' Td',
            $page->contents,
        );
    }

    public function testItMovesAnEntireRowspanGroupToTheNextPage(): void
    {
        $table = Table::define(
            TableColumn::proportional(1.0),
            TableColumn::proportional(1.0),
        )
            ->withCellPadding(CellPadding::all(6.0))
            ->withTextOptions(new TextOptions(fontSize: 12.0, lineHeight: 14.4))
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('Merged', rowspan: 2),
                    TableCell::text('Top'),
                ),
                TableRow::fromCells(
                    TableCell::text('Bottom'),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->text("Lead 1\nLead 2\nLead 3\nLead 4\nLead 5\nLead 6\nLead 7\nLead 8\nLead 9", new TextOptions(fontSize: 18.0, lineHeight: 18.0))
            ->table($table)
            ->build();
        $font = StandardFontDefinition::from('Helvetica');
        $firstRowTextY = $document->pages[1]->contentArea()->top - 6.0 - $font->ascent(12.0);
        $firstColumnX = $document->pages[1]->contentArea()->left + 6.0;
        $secondColumnX = $document->pages[1]->contentArea()->left + ($document->pages[1]->contentArea()->width() / 2) + 6.0;

        self::assertCount(2, $document->pages);
        self::assertStringContainsString('[<4c>', $document->pages[0]->contents);
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($firstRowTextY) . ' Td',
            $document->pages[1]->contents,
        );
        self::assertStringContainsString(
            $this->formatNumber($secondColumnX) . ' ' . $this->formatNumber($firstRowTextY) . ' Td',
            $document->pages[1]->contents,
        );
    }

    public function testItSupportsMiddleVerticalAlignmentWithinATallerRow(): void
    {
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )
            ->withCellPadding(CellPadding::all(6.0))
            ->withTextOptions(new TextOptions(fontSize: 12.0, lineHeight: 14.4))
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('Center')->withVerticalAlign(VerticalAlign::MIDDLE),
                    TableCell::text("Line one\nLine two\nLine three"),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->build();

        self::assertStringContainsString('62.693 491.783 Td', $document->pages[0]->contents);
        self::assertStringContainsString('142.693 520.583 Td', $document->pages[0]->contents);
    }

    public function testItSupportsBottomVerticalAlignmentWithinATallerRow(): void
    {
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )
            ->withCellPadding(CellPadding::all(6.0))
            ->withTextOptions(new TextOptions(fontSize: 12.0, lineHeight: 14.4))
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('Bottom')->withVerticalAlign(VerticalAlign::BOTTOM),
                    TableCell::text("Line one\nLine two\nLine three"),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->build();

        self::assertStringContainsString('62.693 462.983 Td', $document->pages[0]->contents);
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
