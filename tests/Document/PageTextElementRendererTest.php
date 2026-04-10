<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Layout\Text\PageTextElementRenderer;
use Kalle\Pdf\Internal\Page\Annotation\PageAnnotations;
use Kalle\Pdf\Internal\Page\Content\PageGraphics;
use Kalle\Pdf\Internal\Page\Content\PageLinks;
use Kalle\Pdf\Internal\Page\Content\PageMarkedContentIds;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Layout\Position;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageTextElementRendererTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    #[Test]
    public function it_marks_text_as_artifact_inside_artifact_context(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();
        $renderer = $this->createRenderer($page);

        PageGraphics::forPage($page)->renderDecorativeContent(static function () use ($renderer): void {
            $renderer->render('Layered', new Position(10, 20), self::pdfUaRegularFont(), 12);
        });

        self::assertStringContainsString('/Artifact BMC', $page->getContents()->render());
    }

    #[Test]
    public function it_ignores_trailing_spaces_for_underlines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $renderer = $this->createRenderer($page);

        $renderer->render('example ', new Position(10, 50), 'Helvetica', 10, new TextOptions(underline: true));

        $expectedUnderlineWidth = $page->measureTextWidth('example', 'Helvetica', 10);
        $formattedWidth = rtrim(rtrim(sprintf('%.6F', $expectedUnderlineWidth), '0'), '.');

        self::assertStringContainsString('(example ) Tj', $page->getContents()->render());
        self::assertStringContainsString("10 48.2 $formattedWidth 0.5 re f", $page->getContents()->render());
    }

    private function createRenderer(Page $page): PageTextElementRenderer
    {
        $pageFonts = PageFonts::forPage($page);

        return PageTextElementRenderer::forPage(
            $page,
            $pageFonts,
            PageLinks::forPage($page, PageAnnotations::forPage($page, $pageFonts)),
            PageGraphics::forPage($page),
            new PageMarkedContentIds(),
        );
    }
}
