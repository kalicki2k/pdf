<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Table\Style\CellStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableBorder;
use Kalle\Pdf\Document\Table\Style\TablePadding;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    #[Test]
    public function it_keeps_legacy_table_style_aliases_available(): void
    {
        self::assertInstanceOf(TableStyle::class, new \Kalle\Pdf\Styles\TableStyle());
        self::assertInstanceOf(TableBorder::class, \Kalle\Pdf\Styles\TableBorder::all());
        self::assertInstanceOf(TablePadding::class, \Kalle\Pdf\Styles\TablePadding::all(4));
        self::assertInstanceOf(RowStyle::class, new \Kalle\Pdf\Styles\RowStyle());
        self::assertInstanceOf(HeaderStyle::class, new \Kalle\Pdf\Styles\HeaderStyle());
        self::assertInstanceOf(CellStyle::class, new \Kalle\Pdf\Styles\CellStyle());
    }

    #[Test]
    public function it_renders_a_table_row_with_header_and_body_cells(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $table = $page->createTable(20, 260, 170, [50, 70, 50])
            ->headerStyle(new HeaderStyle(
                fillColor: Color::gray(0.9),
                textColor: Color::rgb(255, 0, 0),
            ))
            ->rowStyle(new RowStyle(
                textColor: Color::gray(0.2),
            ))
            ->addRow(['ID', 'Titel', 'Preis'], header: true)
            ->addRow([
                '1',
                new TableCell('Produkt A', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
                [new TextSegment(text: '19,99 EUR', underline: true)],
            ]);

        self::assertSame($page, $table->getPage());
        self::assertStringContainsString("20 236 50 24 re\nf", $page->contents->render());
        self::assertStringContainsString("20 236 50 24 re\nS", $page->contents->render());
        self::assertStringContainsString('/BaseFont /Helvetica', $document->render());
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $document->render());
        self::assertStringContainsString('(Produkt) Tj', $page->contents->render());
        self::assertStringContainsString('(19,99) Tj', $page->contents->render());
    }

    #[Test]
    public function it_renders_cells_with_colspan(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [30, 50, 40, 50])
            ->addRow(['#', 'Titel', 'Status', 'Preis'], header: true)
            ->addRow([
                '1',
                new TableCell(
                    'Zusammengefasste Beschreibung',
                    colspan: 2,
                    style: new CellStyle(horizontalAlign: HorizontalAlign::LEFT),
                ),
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
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [40, 60, 70])
            ->addRow(['Gruppe', 'Titel', 'Status'], header: true)
            ->addRow([
                new TableCell('A', rowspan: 2, style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
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
        self::assertSame(2, substr_count($page->contents->render(), '(Eintra) Tj'));
        self::assertStringContainsString('(Aktiv) Tj', $page->contents->render());
    }

    #[Test]
    public function it_supports_partial_cell_borders(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [85, 85])
            ->style(new TableStyle(border: TableBorder::none()))
            ->addRow([
                new TableCell('Links', style: new CellStyle(border: TableBorder::only(['left', 'bottom'], color: Color::rgb(255, 0, 0)))),
                new TableCell('Rechts', style: new CellStyle(border: TableBorder::horizontal(color: Color::gray(0.5)))),
            ]);

        self::assertStringContainsString("1 0 0 RG\n1 w\n20 236 m\n20 260 l\nS", $page->contents->render());
        self::assertStringContainsString("1 0 0 RG\n1 w\n20 236 m\n105 236 l\nS", $page->contents->render());
        self::assertStringContainsString("0.5 G\n1 w\n105 260 m\n190 260 l\nS", $page->contents->render());
    }

    #[Test]
    public function it_merges_cell_borders_with_the_table_default_border(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [85, 85])
            ->addRow([
                new TableCell('Links', style: new CellStyle(border: TableBorder::only(['left'], color: Color::rgb(0, 255, 0)))),
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
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [85, 85])
            ->style(new TableStyle(verticalAlign: VerticalAlign::MIDDLE))
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
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [85, 85])
            ->style(new TableStyle(padding: TablePadding::symmetric(10, 4)))
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
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [170])
            ->style(new TableStyle(padding: TablePadding::all(6)))
            ->addRow([
                new TableCell('Override', style: new CellStyle(padding: TablePadding::only(top: 2, right: 4, bottom: 8, left: 20))),
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("20 238 170 22 re\nS", $contents);
        self::assertStringContainsString("40 246 Td\n(Override) Tj", $contents);
    }

    #[Test]
    public function it_supports_cell_styles(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [170])
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
    public function it_supports_table_styles_as_defaults(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [170])
            ->style(new TableStyle(
                padding: TablePadding::symmetric(10, 4),
                border: TableBorder::all(color: Color::rgb(255, 0, 0)),
                verticalAlign: VerticalAlign::MIDDLE,
            ))
            ->addRow([
                new TableCell(
                    'Styled',
                    style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER),
                ),
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("1 0 0 RG\n1 w\n20 240 170 20 re\nS", $contents);
        self::assertStringContainsString("83.4 244 Td\n(Styled) Tj", $contents);
    }

    #[Test]
    public function it_supports_row_styles_between_table_and_cell_defaults(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [85, 85])
            ->style(new TableStyle(
                padding: TablePadding::all(6),
                border: TableBorder::all(color: Color::gray(0.75)),
                verticalAlign: VerticalAlign::TOP,
            ))
            ->rowStyle(new RowStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                verticalAlign: VerticalAlign::MIDDLE,
                fillColor: Color::gray(0.9),
                textColor: Color::rgb(255, 0, 0),
                border: TableBorder::horizontal(color: Color::rgb(0, 0, 255)),
            ))
            ->addRow([
                'Links',
                new TableCell('Rechts', style: new CellStyle(fillColor: Color::gray(0.8))),
            ]);

        $contents = $page->contents->render();

        self::assertStringContainsString("20 236 85 24 re\nf", $contents);
        self::assertStringContainsString("0 0 1 RG\n1 w\n20 260 m\n105 260 l\nS", $contents);
        self::assertStringContainsString('44.5 242 Td', $contents);
        self::assertStringContainsString('1 0 0 rg', $contents);
        self::assertStringContainsString("105 236 85 24 re\nf", $contents);
    }

    #[Test]
    public function it_allows_cells_to_override_the_table_vertical_alignment(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(20, 260, 170, [85, 85])
            ->style(new TableStyle(verticalAlign: VerticalAlign::MIDDLE))
            ->addRow([
                new TableCell('Kurz', style: new CellStyle(verticalAlign: VerticalAlign::BOTTOM)),
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
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(200, 200);

        $table = $page->createTable(20, 120, 160, [80, 80], 20)
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
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(20, 260, 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table row spans must match the number of columns.');

        $table->addRow(['nur eine Zelle']);
    }

    #[Test]
    public function it_rejects_cells_with_invalid_colspan(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(20, 260, 170, [85, 85]);

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
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(20, 260, 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table cell rowspan must be greater than zero.');

        $table->addRow([
            new TableCell('A', rowspan: 0),
            'Wert',
        ]);
    }

    #[Test]
    public function it_renders_rowspan_groups_across_page_boundaries(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(200, 120);

        $table = $page->createTable(20, 90, 160, [80, 80], 20)
            ->font('Helvetica', 12)
            ->addRow(['Gruppe', 'Wert'], header: true)
            ->addRow([
                new TableCell('A', rowspan: 3, style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
                'Eintrag 1',
            ])
            ->addRow(['Eintrag 2'])
            ->addRow(['Eintrag 3']);

        $renderedDocument = $document->render();

        self::assertNotSame($page, $table->getPage());
        self::assertStringContainsString('/Count 3', $renderedDocument);
        self::assertSame(1, substr_count($renderedDocument, '(A) Tj'));
        self::assertStringContainsString('(Eintrag 1) Tj', $renderedDocument);
        self::assertStringContainsString('(Eintrag 2) Tj', $renderedDocument);
        self::assertStringContainsString('(Eintrag 3) Tj', $renderedDocument);
        self::assertStringNotContainsString("20 42 80 24 re\nS", $renderedDocument);
    }

    #[Test]
    public function it_continues_rowspan_text_across_page_boundaries(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(200, 140);

        $page->createTable(20, 118, 160, [70, 90], 20)
            ->font('Helvetica', 12)
            ->addRow(['Beschreibung', 'Wert'], header: true)
            ->addRow([
                new TableCell(
                    'Alpha Beta Gamma Delta Epsilon Zeta Eta Theta Iota',
                    rowspan: 4,
                    style: new CellStyle(horizontalAlign: HorizontalAlign::LEFT),
                ),
                'Eintrag 1',
            ])
            ->addRow(['Eintrag 2'])
            ->addRow(['Eintrag 3'])
            ->addRow(['Eintrag 4']);

        $renderedDocument = $document->render();

        self::assertStringContainsString('(Alpha) Tj', $renderedDocument);
        self::assertStringContainsString('(Gamma) Tj', $renderedDocument);
        self::assertStringContainsString('(Delta) Tj', $renderedDocument);
        self::assertStringContainsString('/Count 3', $renderedDocument);
    }
}
