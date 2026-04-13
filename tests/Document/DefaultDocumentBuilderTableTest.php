<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function implode;
use function number_format;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\VerticalAlign;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
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
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding($padding)
                    ->withBorder(Border::all(0.5))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
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

    public function testItUsesExplicitTablePlacementAndCellPaddingOverrides(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withPlacement(new TablePlacement(70.0, 180.0))
                    ->withCellPadding(CellPadding::all(4.0)),
            )
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('Left')->withPadding(CellPadding::symmetric(10.0, 12.0)),
                    TableCell::text('Right'),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->table($table)
            ->build();

        $page = $document->pages[0];
        $font = StandardFontDefinition::from('Helvetica');

        self::assertStringContainsString(
            '0.5 w' . "\n" . '70 ' . $this->formatNumber($page->contentArea()->top) . ' m',
            $page->contents,
        );
        self::assertStringContainsString(
            '82 ' . $this->formatNumber($page->contentArea()->top - 10.0 - $font->ascent(12.0)) . ' Td',
            $page->contents,
        );
        self::assertStringContainsString(
            '164 ' . $this->formatNumber($page->contentArea()->top - 4.0 - $font->ascent(12.0)) . ' Td',
            $page->contents,
        );
    }

    public function testItUsesExplicitTablePlacementYOnTheCurrentPage(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
        )
            ->withOptions((TableOptions::make())->withPlacement(TablePlacement::at(70.0, 460.0, 180.0)))
            ->withRows(
                TableRow::fromTexts('Left', 'Right'),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->table($table)
            ->build();
        $page = $document->pages[0];
        $font = StandardFontDefinition::from('Helvetica');

        self::assertStringContainsString(
            '70 460 m',
            $page->contents,
        );
        self::assertStringContainsString(
            '74 ' . $this->formatNumber(460.0 - 4.0 - $font->ascent(12.0)) . ' Td',
            $page->contents,
        );
    }

    public function testItRejectsExplicitTablePlacementYAboveTheCurrentFlowCursor(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
        )
            ->withOptions((TableOptions::make())->withPlacement(TablePlacement::at(60.0, 550.0, 90.0)))
            ->withRows(
                TableRow::fromTexts('Value'),
            );

        try {
            DefaultDocumentBuilder::make()
                ->pageSize(PageSize::A5())
                ->margin(Margin::all(24.0))
                ->text('Intro text', TextOptions::make(
                    fontSize: 18.0,
                    lineHeight: 22.0,
                ))
                ->table($table);
            self::fail('Expected coded table layout validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TABLE_LAYOUT_INVALID, $exception->error);
            self::assertSame(
                'Explicit table placement y must not be above the current flow cursor on the page.',
                $exception->getMessage(),
            );
        }
    }

    public function testItRejectsTablePlacementLeftOfThePageContentArea(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
        )
            ->withOptions((TableOptions::make())->withPlacement(new TablePlacement(10.0, 90.0)))
            ->withRows(
                TableRow::fromTexts('Value'),
            );

        try {
            DefaultDocumentBuilder::make()
                ->pageSize(PageSize::A5())
                ->margin(Margin::all(24.0))
                ->table($table);
            self::fail('Expected coded table layout validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TABLE_LAYOUT_INVALID, $exception->error);
            self::assertSame(
                'Table placement x must not start left of the page content area.',
                $exception->getMessage(),
            );
        }
    }

    public function testItRejectsTablePlacementWidthBeyondThePageContentArea(): void
    {
        $table = Table::define(
            TableColumn::fixed(360.0),
        )
            ->withOptions((TableOptions::make())->withPlacement(new TablePlacement(70.0, 360.0)))
            ->withRows(
                TableRow::fromTexts('Value'),
            );

        try {
            DefaultDocumentBuilder::make()
                ->pageSize(PageSize::A5())
                ->margin(Margin::all(24.0))
                ->table($table);
            self::fail('Expected coded table layout validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TABLE_LAYOUT_INVALID, $exception->error);
            self::assertSame(
                'Table placement width exceeds the page content area.',
                $exception->getMessage(),
            );
        }
    }

    public function testItRejectsTablePlacementYOutsideThePageContentArea(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
        )
            ->withOptions((TableOptions::make())->withPlacement(TablePlacement::at(60.0, 20.0, 90.0)))
            ->withRows(
                TableRow::fromTexts('Value'),
            );

        try {
            DefaultDocumentBuilder::make()
                ->pageSize(PageSize::A5())
                ->margin(Margin::all(24.0))
                ->table($table);
            self::fail('Expected coded table layout validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TABLE_LAYOUT_INVALID, $exception->error);
            self::assertSame(
                'Table placement y must stay within the page content area.',
                $exception->getMessage(),
            );
        }
    }

    public function testItAppliesHorizontalAlignmentAndCellSpecificBorders(): void
    {
        $font = StandardFontDefinition::from('Helvetica');
        $cellTextWidth = $font->measureTextWidth('7', 12.0);
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withPlacement(new TablePlacement(60.0, 160.0))
                    ->withBorder(Border::none())
                    ->withCellPadding(CellPadding::all(6.0)),
            )
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('7')->withHorizontalAlign(TextAlign::RIGHT),
                    TableCell::text('Border')->withBorder(Border::all(1.0)),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->table($table)
            ->build();

        $page = $document->pages[0];
        $rightAlignedX = 60.0 + 6.0 + (68.0 - $cellTextWidth);

        self::assertStringContainsString(
            $this->formatNumber($rightAlignedX) . ' ' . $this->formatNumber($page->contentArea()->top - 6.0 - $font->ascent(12.0)) . ' Td',
            $page->contents,
        );
        self::assertStringContainsString(
            '1 w' . "\n" . '140 ' . $this->formatNumber($page->contentArea()->top) . ' m',
            $page->contents,
        );
        self::assertStringNotContainsString(
            '0.5 w' . "\n" . '60 ' . $this->formatNumber($page->contentArea()->top) . ' m',
            $page->contents,
        );
    }

    public function testItRendersRichTextSegmentsInsideTableCells(): void
    {
        $table = Table::define(
            TableColumn::fixed(180.0),
        )
            ->withOptions((TableOptions::make())->withPlacement(TablePlacement::at(60.0, 480.0, 180.0)))
            ->withRows(
                TableRow::fromCells(
                    TableCell::segments(
                        TextSegment::plain('Read the '),
                        TextSegment::link('documentation', TextLink::externalUrl('https://example.com/docs')),
                        TextSegment::plain(' before rollout.'),
                    ),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->table($table)
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        self::assertSame('documentation', $document->pages[0]->annotations[0]->contents);
        self::assertGreaterThanOrEqual(5, substr_count($document->pages[0]->contents, "BT\n/F1 12 Tf\n"));
    }

    public function testItKeepsVeryNarrowColumnTablesDeterministic(): void
    {
        $table = Table::define(
            TableColumn::fixed(30.0),
            TableColumn::fixed(34.0),
            TableColumn::fixed(42.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withPlacement(TablePlacement::at(40.0, 360.0, 106.0))
                    ->withCellPadding(CellPadding::symmetric(4.0, 3.0)),
            )
            ->withRows(
                TableRow::fromTexts('Area', 'Queue', 'INC2026ALPHAOMEGA0004711'),
                TableRow::fromTexts('South', '', 'REGIONALHANDOVERALPHA2026040801'),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A6())
            ->margin(Margin::all(18.0))
            ->table($table)
            ->build();

        self::assertCount(1, $document->pages);
        self::assertSame(5, substr_count($document->pages[0]->contents, "BT\n/F1 12 Tf\n"));
    }

    public function testItRepeatsAHeaderMatrixAcrossPages(): void
    {
        $rows = [];

        for ($index = 1; $index <= 12; $index++) {
            $rows[] = TableRow::fromTexts('Region ' . $index, '98 %', '1.2 h', '2', '18');
        }

        $table = Table::define(
            TableColumn::fixed(40.0),
            TableColumn::fixed(45.0),
            TableColumn::fixed(45.0),
            TableColumn::fixed(45.0),
            TableColumn::fixed(45.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withPlacement(TablePlacement::at(20.0, 360.0, 220.0))
                    ->withRepeatedHeaderOnPageBreak()
                    ->withCellPadding(CellPadding::symmetric(4.0, 3.0))
                    ->withTextOptions(TextOptions::make(fontSize: 10.0, lineHeight: 12.0)),
            )
            ->withHeaderRows(
                TableRow::fromCells(
                    TableCell::text('Region', rowspan: 2),
                    TableCell::text('Service quality', colspan: 2),
                    TableCell::text('Follow-up', colspan: 2),
                ),
                TableRow::fromTexts('Availability', 'Response time', 'Escalations', 'Resolved'),
            )
            ->withRows(...$rows);
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A6())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();

        self::assertGreaterThan(1, count($document->pages));
        self::assertCount(2, $document->pages);
        self::assertSame(26, substr_count($document->pages[1]->contents, "BT\n/F1 10 Tf\n"));
    }

    public function testItSplitsSpanHeavyTablesAcrossPagesDeterministically(): void
    {
        $rows = [];

        foreach (['North', 'South', 'West'] as $region) {
            $rows[] = TableRow::fromCells(
                TableCell::text($region, rowspan: 2),
                TableCell::text('Availability review'),
                TableCell::text('98 %'),
                TableCell::text('97 %'),
                TableCell::text('99 %'),
            );
            $rows[] = TableRow::fromCells(
                TableCell::text('Follow-up action'),
                TableCell::text('1.2 h'),
                TableCell::text('1.1 h'),
                TableCell::text('1.0 h'),
            );
            $rows[] = TableRow::fromCells(
                TableCell::text($region . ' summary'),
                TableCell::text($region . ' remains stable overall, but rollout notes and acknowledgements stay grouped.', colspan: 4),
            );
        }

        $table = Table::define(
            TableColumn::fixed(28.0),
            TableColumn::fixed(34.0),
            TableColumn::fixed(34.0),
            TableColumn::fixed(34.0),
            TableColumn::fixed(34.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withPlacement(TablePlacement::at(20.0, 360.0, 164.0))
                    ->withRepeatedHeaderOnPageBreak()
                    ->withCellPadding(CellPadding::symmetric(4.0, 3.0))
                    ->withTextOptions(TextOptions::make(fontSize: 9.0, lineHeight: 11.5)),
            )
            ->withHeaderRows(TableRow::fromTexts('Region', 'Metric', 'Jan', 'Feb', 'Mar'))
            ->withRows(...$rows);
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A6())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();

        self::assertGreaterThan(1, count($document->pages));
        self::assertCount(2, $document->pages);
        self::assertSame(50, substr_count($document->pages[0]->contents, "BT\n/F1 9 Tf\n"));
        self::assertSame(11, substr_count($document->pages[1]->contents, "BT\n/F1 9 Tf\n"));
    }

    public function testItRendersATableCaptionBeforeTheHeaderAndBody(): void
    {
        $table = Table::define(
            TableColumn::fixed(100.0),
            TableColumn::fixed(100.0),
        )
            ->withOptions((TableOptions::make())->withCaption(TableCaption::text('Inventory summary')->withSpacingAfter(10.0)))
            ->withHeaderRows(TableRow::fromTexts('Name', 'Value'))
            ->withRows(TableRow::fromTexts('Alpha', 'Beta'));
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->build();

        $contents = $document->pages[0]->contents;
        $captionPositionSnippet = "BT\n/F1 12 Tf\n56.693 526.583 Td";
        $headerPositionSnippet = "BT\n/F1 12 Tf\n60.693 498.183 Td";

        self::assertStringContainsString($captionPositionSnippet, $contents);
        self::assertStringContainsString($headerPositionSnippet, $contents);
        self::assertLessThan(strpos($contents, $headerPositionSnippet), strpos($contents, $captionPositionSnippet));
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
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
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
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
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
            ->withOptions(
                (TableOptions::make())
                    ->withRepeatedHeaderOnPageBreak()
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
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

    public function testItRendersFooterRowsAfterTheBody(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
        )
            ->withRows(
                TableRow::fromTexts('Alpha', '1'),
                TableRow::fromTexts('Beta', '2'),
            )
            ->withFooterRows(
                TableRow::fromTexts('Total', '3'),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->build();

        self::assertStringContainsString("BT\n/F1 12 Tf\n60.693 477.783 Td", $document->pages[0]->contents);
        self::assertGreaterThan(
            strpos($document->pages[0]->contents, "BT\n/F1 12 Tf\n60.693 500.183 Td"),
            strpos($document->pages[0]->contents, "BT\n/F1 12 Tf\n60.693 477.783 Td"),
        );
    }

    public function testItDoesNotRepeatFooterRowsByDefault(): void
    {
        $rows = [];

        for ($index = 1; $index <= 10; $index++) {
            $rows[] = TableRow::fromTexts('Item ' . $index, 'Value ' . $index);
        }

        $table = Table::define(
            TableColumn::proportional(1.0),
            TableColumn::proportional(1.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
            ->withRows(...$rows)
            ->withFooterRows(TableRow::fromTexts('Foot Left', 'Foot Right'));
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();

        self::assertCount(2, $document->pages);
        self::assertStringNotContainsString($this->encodedFootLeftSnippet(), $document->pages[0]->contents);
        self::assertStringContainsString($this->encodedFootLeftSnippet(), $document->pages[1]->contents);
    }

    public function testItRepeatsFooterRowsOnFollowUpPagesWhenEnabled(): void
    {
        $rows = [];

        for ($index = 1; $index <= 10; $index++) {
            $rows[] = TableRow::fromTexts('Item ' . $index, 'Value ' . $index);
        }

        $table = Table::define(
            TableColumn::proportional(1.0),
            TableColumn::proportional(1.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4))
                    ->withRepeatedFooterOnPageBreak(),
            )
            ->withRows(...$rows)
            ->withFooterRows(TableRow::fromTexts('Foot Left', 'Foot Right'));
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();

        self::assertCount(2, $document->pages);
        self::assertStringContainsString($this->encodedFootLeftSnippet(), $document->pages[0]->contents);
        self::assertStringContainsString($this->encodedFootLeftSnippet(), $document->pages[1]->contents);
    }

    public function testItRendersAColspanCellAcrossMultipleColumns(): void
    {
        $padding = CellPadding::all(6.0);
        $table = Table::define(
            TableColumn::fixed(60.0),
            TableColumn::fixed(70.0),
            TableColumn::fixed(80.0),
        )
            ->withOptions((TableOptions::make())->withCellPadding($padding))
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
            ->withOptions((TableOptions::make())->withCellPadding($padding))
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
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
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
            ->text("Lead 1\nLead 2\nLead 3\nLead 4\nLead 5\nLead 6\nLead 7\nLead 8\nLead 9", TextOptions::make(fontSize: 18.0, lineHeight: 18.0))
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
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
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

        self::assertStringContainsString('62.693 506.183 Td', $document->pages[0]->contents);
        self::assertStringContainsString('142.693 520.583 Td', $document->pages[0]->contents);
    }

    public function testItSupportsBottomVerticalAlignmentWithinATallerRow(): void
    {
        $table = Table::define(
            TableColumn::fixed(80.0),
            TableColumn::fixed(80.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
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

        self::assertStringContainsString('62.693 491.783 Td', $document->pages[0]->contents);
    }

    public function testItSplitsATallSingleRowAcrossMultiplePages(): void
    {
        $table = Table::define(
            TableColumn::proportional(1.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
            ->withRows(
                TableRow::fromTexts($this->multilineText(18)),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();
        $firstColumnX = $document->pages[0]->contentArea()->left + 6.0;

        self::assertGreaterThanOrEqual(2, count($document->pages));
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ',
            $document->pages[0]->contents,
        );
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ',
            $document->pages[1]->contents,
        );
    }

    public function testItSplitsATallRowspanGroupAcrossMultiplePages(): void
    {
        $table = Table::define(
            TableColumn::proportional(1.0),
            TableColumn::proportional(1.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
            ->withRows(
                TableRow::fromCells(
                    TableCell::text($this->multilineText(18), rowspan: 2),
                    TableCell::text('Top'),
                ),
                TableRow::fromCells(
                    TableCell::text('Bottom'),
                ),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();
        $firstColumnX = $document->pages[0]->contentArea()->left + 6.0;

        self::assertGreaterThanOrEqual(2, count($document->pages));
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ',
            $document->pages[0]->contents,
        );
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ',
            $document->pages[1]->contents,
        );
    }

    public function testItRepeatsHeadersWhenATallRowIsSplitAcrossPages(): void
    {
        $table = Table::define(
            TableColumn::proportional(1.0),
        )
            ->withHeaderRows(TableRow::fromTexts('Header'))
            ->withOptions(
                (TableOptions::make())
                    ->withRepeatedHeaderOnPageBreak()
                    ->withCellPadding(CellPadding::all(6.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
            ->withRows(
                TableRow::fromTexts($this->multilineText(18)),
            );
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->table($table)
            ->build();
        $font = StandardFontDefinition::from('Helvetica');
        $firstColumnX = $document->pages[0]->contentArea()->left + 6.0;
        $headerTextY = $document->pages[0]->contentArea()->top - 6.0 - $font->ascent(12.0);

        self::assertGreaterThanOrEqual(2, count($document->pages));
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($headerTextY) . ' Td',
            $document->pages[0]->contents,
        );
        self::assertStringContainsString(
            $this->formatNumber($firstColumnX) . ' ' . $this->formatNumber($headerTextY) . ' Td',
            $document->pages[1]->contents,
        );
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function multilineText(int $lineCount): string
    {
        $lines = [];

        for ($index = 1; $index <= $lineCount; $index++) {
            $lines[] = 'Line ' . $index;
        }

        return implode("\n", $lines);
    }

    private function encodedFootLeftSnippet(): string
    {
        return '[<46> 21 <6f> <6f> 9 <74> <20> <4c> 12 <65> 10 <66> -23 <74>] TJ';
    }
}
