<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Feature\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Feature\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Feature\Table\Rendering\TableGroupSegmentRenderer;
use Kalle\Pdf\Feature\Table\Rendering\TableRenderContext;
use Kalle\Pdf\Feature\Table\Rendering\TableStructElemFactory;
use Kalle\Pdf\Feature\Table\Style\TableBorder;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Feature\Table\Style\TableStyle;
use Kalle\Pdf\Feature\Table\Support\TableStyleResolver;
use Kalle\Pdf\Feature\Table\Support\TableTextMetrics;
use Kalle\Pdf\Feature\Table\TableCell;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableGroupSegmentRendererTest extends TestCase
{
    #[Test]
    public function it_renders_a_partial_group_and_tracks_rowspan_continuations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(200, 200);
        $styleResolver = new TableStyleResolver();
        $renderer = new TableGroupSegmentRenderer(
            $styleResolver,
            new TableStructElemFactory(),
        );
        $preparedCellRenderer = $this->createPreparedCellRenderer($styleResolver);

        $result = $renderer->render(
            $page,
            [
                new PreparedTableRow([$this->createPreparedCell('Cell', rowspan: 2)], false),
                new PreparedTableRow([$this->createPreparedCell('Other')], false),
            ],
            [24.0, 24.0],
            1,
            160.0,
            [],
            $this->createRenderContext($preparedCellRenderer),
        );

        self::assertSame($page, $result->page);
        self::assertSame(136.0, $result->cursorY);
        self::assertCount(1, $result->pendingRowspanCells);
        self::assertSame(1, $result->pendingRowspanCells[0]->remainingRows);
        self::assertStringContainsString('(Cell) Tj', $page->getContents()->render());
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

    private function createPreparedCellRenderer(TableStyleResolver $styleResolver): PreparedCellRenderer
    {
        return new PreparedCellRenderer(
            $styleResolver,
            new CellLayoutResolver(20.0, [160.0]),
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

    private function createRenderContext(PreparedCellRenderer $preparedCellRenderer): TableRenderContext
    {
        return new TableRenderContext(
            $preparedCellRenderer,
            $this->createTableStyle(),
            null,
            null,
            null,
            'Helvetica',
            12,
            1.2,
            null,
        );
    }
}
