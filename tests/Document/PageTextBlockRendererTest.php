<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PageFonts;
use Kalle\Pdf\Document\Text\PageTextBlockRenderer;
use Kalle\Pdf\Document\Text\PageTextLineRenderer;
use Kalle\Pdf\Document\Text\TextLayoutEngine;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageTextBlockRendererTest extends TestCase
{
    #[Test]
    public function it_continues_paragraph_lines_on_a_new_page_when_needed(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $firstPage = $document->addPage(100.0, 80.0);
        $pageFonts = PageFonts::forPage($firstPage);
        $lineRenderer = new PageTextLineRenderer($pageFonts, TextLayoutEngine::forPageFonts($pageFonts));
        $renderer = new PageTextBlockRenderer($firstPage, $lineRenderer);

        $lastPage = $renderer->renderParagraphLines(
            [
                ['segments' => [new TextSegment('First')], 'justify' => false],
                ['segments' => [new TextSegment('Second')], 'justify' => false],
            ],
            10.0,
            50.0,
            80.0,
            'Helvetica',
            10,
            null,
            null,
            40.0,
            20.0,
            HorizontalAlign::LEFT,
        );

        self::assertCount(2, $document->getPages());
        self::assertNotSame($firstPage, $lastPage);
        self::assertStringContainsString("10 50 Td\n(First) Tj", $firstPage->contents->render());
        self::assertStringContainsString("10 50 Td\n(Second) Tj", $lastPage->contents->render());
    }

    #[Test]
    public function it_resolves_the_middle_text_box_start_position(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $pageFonts = PageFonts::forPage($page);
        $renderer = new PageTextBlockRenderer($page, new PageTextLineRenderer($pageFonts, TextLayoutEngine::forPageFonts($pageFonts)));

        self::assertSame(
            37.0,
            $renderer->resolveTextBoxStartY(20.0, 30.0, 10, 12.0, 2, VerticalAlign::MIDDLE, 2.0, 4.0),
        );
    }
}
