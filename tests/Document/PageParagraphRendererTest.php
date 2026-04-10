<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Layout\Geometry\Insets;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Layout\Text\Input\TextBoxOptions;
use Kalle\Pdf\Internal\Layout\Text\PageParagraphRenderer;
use Kalle\Pdf\Internal\Layout\Value\TextOverflow;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageParagraphRendererTest extends TestCase
{
    #[Test]
    public function it_renders_text_box_content_with_padding_and_overflow(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $pageFonts = PageFonts::forPage($page);
        $renderer = PageParagraphRenderer::forPage($page, $pageFonts);

        $renderer->addTextBox(
            'Hello world from PDF',
            new Rect(10, 20, 50, 24),
            'Helvetica',
            10,
            new TextBoxOptions(
                lineHeight: 12,
                overflow: TextOverflow::ELLIPSIS,
                padding: new Insets(2, 5, 2, 5),
            ),
        );

        self::assertStringContainsString("15 32 Td\n(Hello\x85) Tj", $page->getContents()->render());
    }
}
