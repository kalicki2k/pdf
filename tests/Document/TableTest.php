<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TextAlign;
use Kalle\Pdf\Document\TextSegment;
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

        $table = $page->table(20, 260, 170, [50, 70, 50])
            ->headerStyle(Color::gray(0.9), Color::rgb(255, 0, 0))
            ->rowStyle(null, Color::gray(0.2))
            ->addRow(['ID', 'Titel', 'Preis'], header: true)
            ->addRow([
                '1',
                new TableCell('Produkt A', TextAlign::CENTER),
                [new TextSegment(text: '19,99 EUR', underline: true)],
            ]);

        self::assertSame($page, $table->getPage());
        self::assertStringContainsString("20 236 50 24 re\nB", $page->contents->render());
        self::assertStringContainsString('/BaseFont /Helvetica', $document->render());
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $document->render());
        self::assertStringContainsString('(Produkt) Tj', $page->contents->render());
        self::assertStringContainsString('(A) Tj', $page->contents->render());
        self::assertStringContainsString('(19,99) Tj', $page->contents->render());
        self::assertStringContainsString('(EUR) Tj', $page->contents->render());
    }

    #[Test]
    public function it_moves_the_table_to_a_new_page_when_the_next_row_does_not_fit(): void
    {
        $document = new Document(version: 1.4);
        $document
            ->addFont('Helvetica')
            ->addFont('Helvetica-Bold');
        $page = $document->addPage(200, 200);

        $table = $page->table(20, 45, 160, [80, 80], 20)
            ->addRow(['Kopf', 'Wert'], header: true)
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
        $table = $page->table(20, 260, 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table row cell count must match the number of columns.');

        $table->addRow(['nur eine Zelle']);
    }
}
