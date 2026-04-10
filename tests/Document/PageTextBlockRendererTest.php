<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Layout\Text\PageTextBlockRenderer;
use Kalle\Pdf\Internal\Layout\Text\PageTextLineRenderer;
use Kalle\Pdf\Internal\Layout\Text\TextLayoutEngine;
use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Text\TextSegment;
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
        self::assertStringContainsString("10 50 Td\n(First) Tj", $firstPage->getContents()->render());
        self::assertStringContainsString("10 50 Td\n(Second) Tj", $lastPage->getContents()->render());
    }
}
