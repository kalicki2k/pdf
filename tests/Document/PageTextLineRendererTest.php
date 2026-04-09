<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PageFonts;
use Kalle\Pdf\Feature\Text\PageTextLineRenderer;
use Kalle\Pdf\Feature\Text\TextLayoutEngine;
use Kalle\Pdf\Feature\Text\TextSegment;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageTextLineRendererTest extends TestCase
{
    #[Test]
    public function it_renders_a_center_aligned_line(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $pageFonts = PageFonts::forPage($page);
        $renderer = new PageTextLineRenderer($pageFonts, TextLayoutEngine::forPageFonts($pageFonts));
        $font = $pageFonts->resolveFont('Helvetica');
        $expectedX = 10.0 + max(0.0, (40.0 - $font->measureTextWidth('Hello', 10)) / 2);

        $renderer->render(
            $page,
            ['segments' => [new TextSegment('Hello')], 'justify' => false],
            10.0,
            50.0,
            40.0,
            'Helvetica',
            10,
            null,
            null,
            HorizontalAlign::CENTER,
        );

        self::assertStringContainsString(
            sprintf("%s 50 Td\n(Hello) Tj", $this->format($expectedX)),
            $page->getContents()->render(),
        );
    }

    #[Test]
    public function it_renders_a_justified_line_with_distributed_word_spacing(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $pageFonts = PageFonts::forPage($page);
        $renderer = new PageTextLineRenderer($pageFonts, TextLayoutEngine::forPageFonts($pageFonts));
        $font = $pageFonts->resolveFont('Helvetica');
        $lineWidth = $font->measureTextWidth('Hello world', 10);
        $extraWordSpacing = 80.0 - $lineWidth;
        $expectedSecondX = 10.0
            + $font->measureTextWidth('Hello', 10)
            + $font->measureTextWidth(' ', 10)
            + $extraWordSpacing;

        $renderer->render(
            $page,
            ['segments' => [new TextSegment('Hello world')], 'justify' => true],
            10.0,
            50.0,
            80.0,
            'Helvetica',
            10,
            null,
            null,
            HorizontalAlign::JUSTIFY,
        );

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", $page->getContents()->render());
        self::assertStringContainsString(
            sprintf("%s 50 Td\n(world) Tj", $this->format($expectedSecondX)),
            $page->getContents()->render(),
        );
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
