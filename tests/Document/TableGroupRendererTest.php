<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Internal\Layout\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableGroupRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableRenderContext;
use Kalle\Pdf\Internal\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Internal\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Internal\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Internal\Layout\Table\Support\TableTextMetrics;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableGroupRendererTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    #[Test]
    public function it_renders_a_prepared_row_group_and_advances_the_cursor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(200, 200);
        $renderer = new TableGroupRenderer();

        $result = $renderer->render(
            $page,
            new PreparedTableRowGroup(
                [new PreparedTableRow([$this->createPreparedCell('Cell')], false)],
                [24.0],
            ),
            160.0,
            $this->createRenderContext($this->createPreparedCellRenderer()),
        );

        self::assertSame($page, $result->page);
        self::assertSame(136.0, $result->cursorY);
        self::assertStringContainsString('(Cell) Tj', $page->getContents()->render());
    }

    #[Test]
    public function it_creates_structured_table_cells_for_tagged_documents(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Table Group');
        $page = $document->addPage(200, 200);
        $tableStructElem = $document->createStructElem(StructureTag::Table);
        $renderer = new TableGroupRenderer();

        $renderer->render(
            $page,
            new PreparedTableRowGroup(
                [new PreparedTableRow([$this->createPreparedCell('Header')], true)],
                [24.0],
            ),
            160.0,
            $this->createRenderContext(
                $this->createPreparedCellRenderer(x: 20.0, width: 160.0),
                self::pdfUaRegularFont(),
                $tableStructElem,
            ),
        );

        $rendered = $document->render();

        self::assertStringContainsString('/Type /StructElem /S /TR', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /TH', $rendered);
        self::assertStringContainsString('/Scope /Column', $rendered);
    }

    private function createPreparedCell(string $text, int $rowspan = 1): PreparedTableCell
    {
        return new PreparedTableCell(
            new TableCell($text, rowspan: $rowspan),
            160.0,
            0,
            12.0,
            12.0,
            12.0,
            TablePadding::all(6.0),
        );
    }

    private function createPreparedCellRenderer(float $x = 20.0, float $width = 160.0): PreparedCellRenderer
    {
        $styleResolver = new TableStyleResolver();

        return new PreparedCellRenderer(
            $styleResolver,
            new CellLayoutResolver($x, [$width]),
            new CellBoxRenderer($styleResolver),
            new TableTextMetrics(),
        );
    }

    private function createTableStyle(): TableStyle
    {
        return new TableStyle(
            padding: TablePadding::all(6.0),
            border: TableBorder::all(color: Color::gray(0.75)),
            verticalAlign: VerticalAlign::TOP,
        );
    }

    private function createRenderContext(
        PreparedCellRenderer $preparedCellRenderer,
        string $baseFont = 'Helvetica',
        ?StructElem $tableStructElem = null,
    ): TableRenderContext {
        return new TableRenderContext(
            $preparedCellRenderer,
            $this->createTableStyle(),
            null,
            null,
            null,
            $baseFont,
            12,
            1.2,
            $tableStructElem,
        );
    }
}
