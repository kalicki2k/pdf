<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Feature\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Feature\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Feature\Table\Rendering\TableGroupRenderer;
use Kalle\Pdf\Feature\Table\Rendering\TableGroupSegmentRenderer;
use Kalle\Pdf\Feature\Table\Rendering\TablePendingGroupFlow;
use Kalle\Pdf\Feature\Table\Rendering\TablePendingGroupPaginator;
use Kalle\Pdf\Feature\Table\Rendering\TablePendingRenderState;
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

final class TablePendingGroupFlowTest extends TestCase
{
    #[Test]
    public function it_replays_repeating_headers_after_moving_a_pending_group_to_a_new_page(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(200, 120);
        $styleResolver = new TableStyleResolver();
        $preparedCellRenderer = $this->createPreparedCellRenderer($styleResolver);
        $flow = new TablePendingGroupFlow(
            new TablePendingGroupPaginator(),
            new TableGroupRenderer(),
            new TableGroupSegmentRenderer($styleResolver, new TableStructElemFactory()),
        );

        $result = $flow->render(
            $page,
            30.0,
            new PreparedTableRowGroup(
                [new PreparedTableRow([$this->createPreparedCell('Body')], false)],
                [24.0],
            ),
            new PreparedTableRowGroup(
                [new PreparedTableRow([$this->createPreparedCell('Header')], true)],
                [24.0],
            ),
            new TablePendingRenderState(),
            $this->createRenderContext($preparedCellRenderer),
            20.0,
            40.0,
        );

        self::assertNotSame($page, $result->page);
        self::assertCount(2, $document->pages->pages);
        self::assertSame(32.0, $result->cursorY);
        self::assertStringContainsString('(Header) Tj', $result->page->getContents()->render());
        self::assertStringContainsString('(Body) Tj', $result->page->getContents()->render());
    }

    private function createPreparedCell(string $text): PreparedTableCell
    {
        return new PreparedTableCell(
            new TableCell($text),
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

    private function createRenderContext(PreparedCellRenderer $preparedCellRenderer): TableRenderContext
    {
        return new TableRenderContext(
            $preparedCellRenderer,
            new TableStyle(
                padding: TablePadding::all(6.0),
                border: TableBorder::all(color: Color::gray(0.75)),
                verticalAlign: VerticalAlign::TOP,
            ),
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
