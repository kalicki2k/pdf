<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\BulletType;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextFrameTest extends TestCase
{
    #[Test]
    public function it_flows_headings_and_paragraphs_using_a_shared_cursor(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 220, 20);

        $frame
            ->heading('Headline', 'Helvetica', 20, 'H1')
            ->paragraph('Hello world from PDF', 'Helvetica', 10, 'P', spacingAfter: 8);

        self::assertSame($page, $frame->getPage());
        self::assertStringContainsString("20 100 Td\n/H1 << /MCID 0 >> BDC\n(Headline) Tj", $page->contents->render());
        self::assertStringContainsString("20 60 Td\n/P << /MCID 1 >> BDC\n(Hello world from PDF) Tj", $page->contents->render());
        self::assertSame(40.0, $frame->getCursorY());
    }

    #[Test]
    public function it_tracks_the_new_page_after_an_automatic_page_break(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 60);

        $frame = $page->textFrame(10, 30, 40, 15);
        $frame->paragraph('Hello world from PDF', 'Helvetica', 10, spacingAfter: 6);

        self::assertCount(2, $document->pages->pages);
        self::assertSame($document->pages->pages[1], $frame->getPage());
        self::assertGreaterThanOrEqual(15.0, $frame->getCursorY());
    }

    #[Test]
    public function it_can_flow_text_without_structure_tags(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);
        $frame
            ->heading('Headline', 'Helvetica', 20)
            ->paragraph('Hello world from PDF', 'Helvetica', 10, spacingAfter: 8);

        self::assertStringContainsString('(Headline) Tj', $page->contents->render());
        self::assertStringNotContainsString('BDC', $page->contents->render());
    }

    #[Test]
    public function it_forwards_opacity_for_heading_and_paragraph_content(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);
        $frame
            ->heading('Headline', 'Helvetica', 20, opacity: Opacity::fill(0.5))
            ->paragraph('Hello world from PDF', 'Helvetica', 10, spacingAfter: 8, opacity: Opacity::fill(0.5));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.5 >> >>', $page->resources->render());
        self::assertSame(2, substr_count($page->contents->render(), '/GS1 gs'));
    }

    #[Test]
    public function it_forwards_color_for_heading_and_paragraph_content(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);
        $frame
            ->heading('Headline', 'Helvetica', 20, color: Color::rgb(255, 0, 0))
            ->paragraph('Hello world from PDF', 'Helvetica', 10, spacingAfter: 8, color: Color::cmyk(0.1, 0.2, 0.3, 0.4));

        self::assertStringContainsString("1 0 0 rg\n(Headline) Tj", $page->contents->render());
        self::assertStringContainsString("0.1 0.2 0.3 0.4 k\n(Hello world from PDF) Tj", $page->contents->render());
    }

    #[Test]
    public function it_accepts_text_runs_in_text_frames(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);
        $frame->paragraph(
            [
                new TextSegment('Achtung:', Color::rgb(255, 0, 0)),
                new TextSegment(' Hello world from PDF'),
            ],
            'Helvetica',
            10,
            spacingAfter: 8,
        );

        self::assertStringContainsString("1 0 0 rg\n(Achtung:) Tj", $page->contents->render());
        self::assertStringContainsString('( Hello world) Tj', $page->contents->render());
        self::assertStringContainsString('(from PDF) Tj', $page->contents->render());
    }

    #[Test]
    public function it_forwards_center_alignment_from_text_frames(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 100, 20);
        $frame->paragraph('Hello', 'Helvetica', 10, spacingAfter: 8, align: HorizontalAlign::CENTER);

        self::assertStringContainsString("55 100 Td\n(Hello) Tj", $page->contents->render());
    }

    #[Test]
    public function it_forwards_justify_alignment_from_text_frames(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 70, 20);
        $frame->paragraph('Hello world from PDF', 'Helvetica', 10, spacingAfter: 8, align: HorizontalAlign::JUSTIFY);

        self::assertStringContainsString("20 100 Td\n(Hello) Tj", $page->contents->render());
        self::assertStringContainsString("60 100 Td\n(world) Tj", $page->contents->render());
    }

    #[Test]
    public function it_limits_text_frame_paragraphs_to_max_lines_when_requested(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 40, 20);
        $frame->paragraph(
            'Hello world from PDF',
            'Helvetica',
            10,
            spacingAfter: 8,
            maxLines: 2,
            overflow: TextOverflow::ELLIPSIS,
        );

        self::assertStringContainsString("20 100 Td\n(Hello) Tj", $page->contents->render());
        self::assertStringContainsString("20 88 Td\n(wor...) Tj", $page->contents->render());
        self::assertSame(68.0, $frame->getCursorY());
    }

    #[Test]
    public function it_forwards_linked_text_segments_in_text_frames(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);
        $frame->paragraph(
            [
                new TextSegment('Docs', link: 'https://example.com/docs'),
                new TextSegment(' Link', link: 'https://example.com/docs'),
            ],
            'Helvetica',
            10,
            spacingAfter: 8,
        );

        self::assertStringContainsString('/Annots [8 0 R]', $frame->getPage()->render());
        self::assertStringContainsString('/URI (https://example.com/docs)', $document->render());
    }

    #[Test]
    public function it_renders_a_bullet_list_with_hanging_indent(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);
        $frame->bulletList(
            [
                'First bullet item',
                [new TextSegment('Second', bold: true), new TextSegment(' bullet item')],
            ],
            'Helvetica',
            10,
            bulletType: BulletType::DASH,
            spacingAfter: 6,
        );

        self::assertStringContainsString("20 100 Td\n(-) Tj", $page->contents->render());
        self::assertStringContainsString("34 100 Td\n(First bullet item) Tj", $page->contents->render());
        self::assertStringContainsString("20 84 Td\n(-) Tj", $page->contents->render());
        self::assertStringContainsString('(Second) Tj', $page->contents->render());
        self::assertSame(54.0, $frame->getCursorY());
    }

    #[Test]
    public function it_moves_bullet_list_items_to_a_new_page_when_needed(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 60);

        $frame = $page->textFrame(10, 25, 60, 15);
        $frame->bulletList(
            ['First item', 'Second item'],
            'Helvetica',
            10,
            bulletType: BulletType::DASH,
            spacingAfter: 4,
            itemSpacing: 4,
        );

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertStringContainsString("10 25 Td\n(-) Tj", $document->pages->pages[1]->contents->render());
        self::assertStringContainsString('(Second) Tj', $document->render());
        self::assertStringContainsString('(item) Tj', $frame->getPage()->contents->render());
    }

    #[Test]
    public function it_renders_a_numbered_list_with_custom_start_index(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);
        $frame->numberedList(
            [
                'First item',
                [new TextSegment('Second', bold: true), new TextSegment(' item')],
            ],
            'Helvetica',
            10,
            spacingAfter: 6,
            startAt: 3,
        );

        self::assertStringContainsString("20 100 Td\n(3.) Tj", $page->contents->render());
        self::assertStringContainsString("34 100 Td\n(First item) Tj", $page->contents->render());
        self::assertStringContainsString("20 84 Td\n(4.) Tj", $page->contents->render());
        self::assertStringContainsString('(Second) Tj', $page->contents->render());
    }

    #[Test]
    public function it_rejects_numbered_lists_that_start_before_one(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Numbered lists must start at 1 or greater.');

        $frame->numberedList(['One'], 'Helvetica', 10, startAt: 0);
    }
}
