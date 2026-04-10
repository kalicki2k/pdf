<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Internal\Layout\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableFooterRenderer;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableRenderContext;
use Kalle\Pdf\Internal\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Internal\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Internal\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Internal\Layout\Table\Support\TableTextMetrics;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Style\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableFooterRendererTest extends TestCase
{
    #[Test]
    public function it_renders_footer_rows_on_the_current_page_when_they_fit(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(200, 200);
        $renderer = new TableFooterRenderer();

        $result = $renderer->render(
            $page,
            80.0,
            new PreparedTableRowGroup(
                [new PreparedTableRow([$this->createPreparedCell('Summe')], false, true)],
                [24.0],
            ),
            20.0,
            40.0,
            $this->createRenderContext(),
        );

        self::assertSame($page, $result->page);
        self::assertSame(56.0, $result->cursorY);
        self::assertStringContainsString('(Summe) Tj', $page->getContents()->render());
    }

    #[Test]
    public function it_moves_footer_rows_to_a_fresh_page_when_needed(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(200, 120);
        $renderer = new TableFooterRenderer();

        $result = $renderer->render(
            $page,
            30.0,
            new PreparedTableRowGroup(
                [new PreparedTableRow([$this->createPreparedCell('Summe')], false, true)],
                [24.0],
            ),
            20.0,
            40.0,
            $this->createRenderContext(),
        );

        self::assertNotSame($page, $result->page);
        self::assertCount(2, $document->pages->pages);
        self::assertStringContainsString('(Summe) Tj', $result->page->getContents()->render());
    }

    #[Test]
    public function it_rejects_footer_rows_that_do_not_fit_on_a_fresh_page(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(200, 120);
        $renderer = new TableFooterRenderer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table footer rows must fit on a fresh page.');

        $renderer->render(
            $page,
            30.0,
            new PreparedTableRowGroup(
                [new PreparedTableRow([$this->createPreparedCell('Summe')], false, true)],
                [80.0],
            ),
            20.0,
            40.0,
            $this->createRenderContext(),
        );
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

    private function createPreparedCellRenderer(): PreparedCellRenderer
    {
        $styleResolver = new TableStyleResolver();

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

    private function createRenderContext(): TableRenderContext
    {
        return new TableRenderContext(
            $this->createPreparedCellRenderer(),
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
