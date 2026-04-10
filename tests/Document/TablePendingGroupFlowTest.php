<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Internal\Layout\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableGroupRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableGroupSegmentRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TablePendingGroupFlow;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TablePendingGroupPaginator;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TablePendingRenderState;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableRenderContext;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableStructElemFactory;
use Kalle\Pdf\Internal\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Internal\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Internal\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Internal\Layout\Table\Support\TableTextMetrics;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Internal\Style\Color;
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
