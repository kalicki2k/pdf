<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextFrameTest extends TestCase
{
    #[Test]
    public function it_flows_headings_and_paragraphs_using_a_shared_cursor(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);

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
        $document->addFont('Helvetica');
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
        $document->addFont('Helvetica');
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
        $document->addFont('Helvetica');
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
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->textFrame(20, 100, 120, 20);
        $frame
            ->heading('Headline', 'Helvetica', 20, color: Color::rgb(255, 0, 0))
            ->paragraph('Hello world from PDF', 'Helvetica', 10, spacingAfter: 8, color: Color::cmyk(0.1, 0.2, 0.3, 0.4));

        self::assertStringContainsString("1 0 0 rg\n(Headline) Tj", $page->contents->render());
        self::assertStringContainsString("0.1 0.2 0.3 0.4 k\n(Hello world from PDF) Tj", $page->contents->render());
    }
}
