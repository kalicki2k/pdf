<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Writer\StringOutput;
use PHPUnit\Framework\TestCase;

final class TaggedTableStructureTest extends TestCase
{
    public function testItRendersTaggedTableSectionsAndSpanAttributes(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
        )
            ->withPlacement(TablePlacement::at(24.0, 520.0, 270.0))
            ->withCaption(TableCaption::text('Quarterly summary'))
            ->withHeaderRows(
                TableRow::fromCells(
                    TableCell::text('Label', rowspan: 2)->withHeaderScope(TableHeaderScope::BOTH),
                    TableCell::text('Current', colspan: 2),
                ),
                TableRow::fromTexts('Planned', 'Actual'),
            )
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('North', colspan: 2)->withHeaderScope(TableHeaderScope::ROW),
                    TableCell::text('12'),
                ),
                TableRow::fromTexts('South', '11', '10'),
            )
            ->withFooterRows(
                TableRow::fromTexts('Total', '23', '22'),
            );

        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->table($table)
            ->build();

        $output = new StringOutput();
        new DocumentRenderer()->write($document, $output);
        $pdf = $output->contents();

        self::assertStringContainsString('/Type /StructElem /S /Table', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /THead', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TBody', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TFoot', $pdf);
        self::assertStringContainsString('/A << /O /Table /Scope /Both /RowSpan 2 >>', $pdf);
        self::assertStringContainsString('/A << /O /Table /Scope /Column /ColSpan 2 >>', $pdf);
        self::assertStringContainsString('/A << /O /Table /Scope /Row /ColSpan 2 >>', $pdf);
    }
}
