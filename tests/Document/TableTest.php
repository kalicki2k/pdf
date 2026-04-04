<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\CellStyle;
use Kalle\Pdf\Document\TableBorder;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\HorizontalAlign;
use Kalle\Pdf\Document\TablePadding;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Document\VerticalAlign;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    #[Test]
    public function it_renders_a_table_row_with_header_and_body_cells(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->addFont('Helvetica')
            ->addFont('Helvetica-Bold');
        $page = $document->addPage();

        $table = $page->addTable(20, 260, 170, [50, 70, 50])
            ->headerStyle(Color::gray(0.9), Color::rgb(255, 0, 0))
            ->rowStyle(null, Color::gray(0.2))
            ->addRow(['ID', 'Titel', 'Preis'], header: true)
            ->addRow([
                '1',
                new TableCell('Produkt A', HorizontalAlign::CENTER),
                [new TextSegment(text: '19,99 EUR', underline: true)],
            ]);

        self::assertSame($page, $table->getPage());
        self::assertStringContainsString("20 236 50 24 re\nf", $page->contents->render());
        self::assertStringContainsString("20 236 50 24 re\nS", $page->contents->render());
        self::assertStringContainsString('/BaseFont /Helvetica', $document->render());
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $document->render());
        self::assertStringContainsString('(Produkt) Tj', $page->contents->render());
        self::assertStringContainsString('(A) Tj', $page->contents->render());
        self::assertStringContainsString('(19,99) Tj', $page->contents->render());
        self::assertStringContainsString('(EUR) Tj', $page->contents->render());
    }

    #[Test]
    public function it_renders_cells_with_colspan(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->addFont('Helvetica')
            ->addFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [30, 50, 40, 50])
            ->addRow(['#', 'Titel', 'Status', 'Preis'], header: true)
            ->addRow([
                '1',
                new TableCell('Zusammengefasste Beschreibung', HorizontalAlign::LEFT, colspan: 2),
                '19,99 EUR',
            ]);

        self::assertStringContainsString("50 154.4 90 67.2 re\nS", $page->contents->render());
        self::assertStringContainsString('(Zusammenge) Tj', $page->contents->render());
        self::assertStringContainsString('(Beschreibu) Tj', $page->contents->render());
    }

    #[Test]
    public function it_renders_cells_with_rowspan(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->addFont('Helvetica')
            ->addFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [40, 60, 70])
            ->addRow(['Gruppe', 'Titel', 'Status'], header: true)
            ->addRow([
                new TableCell('A', HorizontalAlign::CENTER, rowspan: 2),
                'Eintrag 1',
                'Offen',
            ])
            ->addRow([
                'Eintrag 2',
                'Aktiv',
            ]);

        self::assertStringContainsString("20 144.8 40 76.8 re\nS", $page->contents->render());
        self::assertSame(1, substr_count($page->contents->render(), '(A) Tj'));
        self::assertStringContainsString('(Eintra) Tj', $page->contents->render());
        self::assertStringContainsString('(g 1) Tj', $page->contents->render());
        self::assertStringContainsString('(g 2) Tj', $page->contents->render());
    }

    #[Test]
    public function it_supports_partial_cell_borders(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [85, 85])
            ->borderStyle(TableBorder::none())
            ->addRow([
                new TableCell('Links', border: TableBorder::only(['left', 'bottom'], color: Color::rgb(255, 0, 0))),
                new TableCell('Rechts', border: TableBorder::horizontal(color: Color::gray(0.5))),
            ]);

        self::assertStringContainsString("1 0 0 RG\n1 w\n20 236 m\n20 260 l\nS", $page->contents->render());
        self::assertStringContainsString("1 0 0 RG\n1 w\n20 236 m\n105 236 l\nS", $page->contents->render());
        self::assertStringContainsString("0.5 G\n1 w\n105 260 m\n190 260 l\nS", $page->contents->render());
    }

    #[Test]
    public function it_merges_cell_borders_with_the_table_default_border(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [85, 85])
            ->addRow([
                new TableCell('Links', border: TableBorder::only(['left'], color: Color::rgb(0, 255, 0))),
                'Rechts',
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("0 1 0 RG\n1 w\n20 236 m\n20 260 l\nS", $contents);
        self::assertStringContainsString("0.75 G\n1 w\n20 260 m\n105 260 l\nS", $contents);
        self::assertStringContainsString("0.75 G\n1 w\n20 236 m\n105 236 l\nS", $contents);
        self::assertStringContainsString("0.75 G\n1 w\n105 236 m\n105 260 l\nS", $contents);
    }

    #[Test]
    public function it_supports_middle_vertical_alignment_as_table_default(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [85, 85])
            ->verticalAlign(VerticalAlign::MIDDLE)
            ->addRow([
                new TableCell('Kurz'),
                "Erste Zeile\nZweite Zeile\nDritte Zeile",
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("26 206 Td\n(Kurz) Tj", $contents);
        self::assertStringContainsString("111 242 Td\n(Erste) Tj", $contents);
    }

    #[Test]
    public function it_supports_table_padding_styles(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [85, 85])
            ->paddingStyle(TablePadding::symmetric(10, 4))
            ->addRow([
                'Links',
                'Rechts',
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("20 240 85 20 re\nS", $contents);
        self::assertStringContainsString("30 244 Td\n(Links) Tj", $contents);
        self::assertStringContainsString("115 244 Td\n(Rechts) Tj", $contents);
    }

    #[Test]
    public function it_allows_cells_to_override_the_table_padding(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [170])
            ->paddingStyle(TablePadding::all(6))
            ->addRow([
                new TableCell('Override', padding: TablePadding::only(top: 2, right: 4, bottom: 8, left: 20)),
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("20 238 170 22 re\nS", $contents);
        self::assertStringContainsString("40 246 Td\n(Override) Tj", $contents);
    }

    #[Test]
    public function it_supports_cell_styles(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [170])
            ->addRow([
                new TableCell(
                    'Styled',
                    style: new CellStyle(
                        horizontalAlign: HorizontalAlign::CENTER,
                        verticalAlign: VerticalAlign::MIDDLE,
                        padding: TablePadding::symmetric(10, 4),
                        fillColor: Color::gray(0.9),
                        border: TableBorder::all(color: Color::rgb(255, 0, 0)),
                    ),
                ),
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("20 240 170 20 re\nf", $contents);
        self::assertStringContainsString("1 0 0 RG\n1 w\n20 240 170 20 re\nS", $contents);
        self::assertStringContainsString("83.4 244 Td\n(Styled) Tj", $contents);
    }

    #[Test]
    public function it_allows_cells_to_override_the_table_vertical_alignment(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addTable(20, 260, 170, [85, 85])
            ->verticalAlign(VerticalAlign::MIDDLE)
            ->addRow([
                new TableCell('Kurz', verticalAlign: VerticalAlign::BOTTOM),
                "Erste Zeile\nZweite Zeile\nDritte Zeile",
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("26 170 Td\n(Kurz) Tj", $contents);
        self::assertStringContainsString("111 242 Td\n(Erste) Tj", $contents);
    }

    #[Test]
    public function it_moves_the_table_to_a_new_page_when_the_next_row_does_not_fit(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->addFont('Helvetica')
            ->addFont('Helvetica-Bold');
        $page = $document->addPage(200, 200);

        $table = $page->addTable(20, 120, 160, [80, 80], 20)
            ->addRow(['Kopf', 'Wert'], header: true)
            ->addRow(['A', '1'])
            ->addRow(['B', '2'])
            ->addRow(['C', '3'])
            ->addRow(['Lang', 'Body']);

        self::assertNotSame($page, $table->getPage());
        self::assertCount(2, $document->pages->pages);
        self::assertStringContainsString('(Kopf) Tj', $page->contents->render());
        self::assertStringContainsString('(Kopf) Tj', $table->getPage()->contents->render());
        self::assertStringContainsString('(Body) Tj', $table->getPage()->contents->render());
    }

    #[Test]
    public function it_rejects_rows_with_the_wrong_number_of_cells(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();
        $table = $page->addTable(20, 260, 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table row spans must match the number of columns.');

        $table->addRow(['nur eine Zelle']);
    }

    #[Test]
    public function it_rejects_cells_with_invalid_colspan(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();
        $table = $page->addTable(20, 260, 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table cell colspan must be greater than zero.');

        $table->addRow([
            new TableCell('Ungueltig', colspan: 0),
            'Wert',
        ]);
    }

    #[Test]
    public function it_rejects_cells_with_invalid_rowspan(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();
        $table = $page->addTable(20, 260, 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table cell rowspan must be greater than zero.');

        $table->addRow([
            new TableCell('A', rowspan: 0),
            'Wert',
        ]);
    }
}
