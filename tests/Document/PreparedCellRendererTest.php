<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Layout\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Layout\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Layout\Table\Rendering\CellRenderOptions;
use Kalle\Pdf\Layout\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Layout\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Layout\Table\Support\TableTextMetrics;
use Kalle\Pdf\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PreparedCellRendererTest extends TestCase
{
    #[Test]
    public function it_renders_a_full_cell_via_the_public_render_method(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $renderer = $this->createRenderer();
        $preparedCell = $this->createPreparedCell('Hello world', 80, 18, 14, 16);

        $resultPage = $renderer->render(
            $page,
            $preparedCell,
            false,
            0,
            [30.0],
            100.0,
            14.4,
            new TableStyle(),
            null,
            null,
            'Helvetica',
            12,
        );

        self::assertSame($page, $resultPage);
        self::assertStringContainsString('(Hello world) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_returns_existing_remaining_lines_when_text_rendering_is_disabled(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $renderer = $this->createRenderer();
        $preparedCell = $this->createPreparedCell('Ignored', 80, 18, 14, 16);
        $remainingLines = [[
            'segments' => [new TextSegment('continued')],
            'justify' => false,
        ]];

        $result = $renderer->renderSegment(
            $page,
            $preparedCell,
            $this->createResolvedStyle(),
            0,
            [30.0],
            100.0,
            14.4,
            'Helvetica',
            12,
            new TableStyle(),
            new CellRenderOptions(renderText: false, remainingLines: $remainingLines),
        );

        self::assertSame($page, $result->page);
        self::assertSame($remainingLines, $result->remainingLines);
        self::assertStringNotContainsString('Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_returns_existing_remaining_lines_when_no_text_height_is_available(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $renderer = $this->createRenderer();
        $preparedCell = new PreparedTableCell(
            new TableCell('Ignored'),
            80,
            0,
            18,
            14,
            16,
            TablePadding::only(top: 10, bottom: 12),
        );
        $remainingLines = [[
            'segments' => [new TextSegment('continued')],
            'justify' => false,
        ]];

        $result = $renderer->renderSegment(
            $page,
            $preparedCell,
            $this->createResolvedStyle(padding: TablePadding::only(top: 10, bottom: 12)),
            0,
            [20.0],
            100.0,
            14.4,
            'Helvetica',
            12,
            new TableStyle(),
            new CellRenderOptions(remainingLines: $remainingLines),
        );

        self::assertSame($remainingLines, $result->remainingLines);
        self::assertStringNotContainsString('Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_returns_no_remaining_lines_when_layout_produces_no_lines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $renderer = $this->createRenderer();
        $preparedCell = new PreparedTableCell(
            new TableCell([]),
            80,
            0,
            18,
            14,
            16,
            TablePadding::all(0),
        );

        $result = $renderer->renderSegment(
            $page,
            $preparedCell,
            $this->createResolvedStyle(),
            0,
            [30.0],
            100.0,
            14.4,
            'Helvetica',
            12,
            new TableStyle(),
        );

        self::assertSame([], $result->remainingLines);
        self::assertStringNotContainsString('Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_returns_all_lines_when_a_partial_rowspan_segment_cannot_fit_two_lines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $renderer = $this->createRenderer();
        $preparedCell = new PreparedTableCell(
            new TableCell('Alpha Beta Gamma Delta', rowspan: 2),
            35,
            0,
            18,
            14,
            16,
            TablePadding::all(0),
        );

        $result = $renderer->renderSegment(
            $page,
            $preparedCell,
            $this->createResolvedStyle(),
            0,
            [12.0, 60.0],
            100.0,
            14.4,
            'Helvetica',
            12,
            new TableStyle(),
            new CellRenderOptions(visibleRowspan: 1),
        );

        self::assertGreaterThan(1, count($result->remainingLines));
        self::assertStringNotContainsString('Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_renders_visible_lines_and_returns_the_remaining_lines_for_segments(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $renderer = $this->createRenderer();
        $preparedCell = $this->createPreparedCell('Ignored', 40, 18, 14, 16);
        $remainingLines = [
            ['segments' => [new TextSegment('Line 1')], 'justify' => false],
            ['segments' => [new TextSegment('Line 2')], 'justify' => false],
            ['segments' => [new TextSegment('Line 3')], 'justify' => false],
        ];

        $result = $renderer->renderSegment(
            $page,
            $preparedCell,
            $this->createResolvedStyle(),
            0,
            [40.0],
            100.0,
            14.4,
            'Helvetica',
            12,
            new TableStyle(),
            new CellRenderOptions(remainingLines: $remainingLines),
        );

        self::assertCount(1, $result->remainingLines);
        self::assertSame('Line 3', $result->remainingLines[0]['segments'][0]->text);
        self::assertStringContainsString('(Line 1) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Line 2) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_renders_all_provided_remaining_lines_when_they_fit_completely(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $renderer = $this->createRenderer();
        $preparedCell = $this->createPreparedCell('Ignored', 40, 18, 14, 16);
        $remainingLines = [
            ['segments' => [new TextSegment('Line 1')], 'justify' => false],
            ['segments' => [new TextSegment('Line 2')], 'justify' => false],
        ];

        $result = $renderer->renderSegment(
            $page,
            $preparedCell,
            $this->createResolvedStyle(),
            0,
            [40.0],
            100.0,
            14.4,
            'Helvetica',
            12,
            new TableStyle(),
            new CellRenderOptions(remainingLines: $remainingLines),
        );

        self::assertSame([], $result->remainingLines);
        self::assertStringContainsString('(Line 1) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Line 2) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    private function createRenderer(): PreparedCellRenderer
    {
        $styleResolver = new TableStyleResolver();
        $cellLayoutResolver = new CellLayoutResolver(10.0, [100.0]);

        return new PreparedCellRenderer(
            $styleResolver,
            $cellLayoutResolver,
            new CellBoxRenderer($styleResolver),
            new TableTextMetrics(),
        );
    }

    private function createPreparedCell(string $text, float $width, float $minHeight, float $contentHeight, float $alignmentHeight): PreparedTableCell
    {
        return new PreparedTableCell(
            new TableCell($text),
            $width,
            0,
            $minHeight,
            $contentHeight,
            $alignmentHeight,
            TablePadding::all(0),
        );
    }

    private function createResolvedStyle(?TablePadding $padding = null): ResolvedTableCellStyle
    {
        return new ResolvedTableCellStyle(
            $padding ?? TablePadding::all(0),
            null,
            Color::rgb(0, 0, 255),
            VerticalAlign::TOP,
            HorizontalAlign::LEFT,
            null,
            null,
            null,
        );
    }
}
