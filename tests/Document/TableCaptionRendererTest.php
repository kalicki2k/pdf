<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TableCaptionRenderer;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Table\TableCaption;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableCaptionRendererTest extends TestCase
{
    #[Test]
    public function it_renders_a_caption_on_the_current_page_and_advances_the_cursor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(200, 200);
        $renderer = new TableCaptionRenderer();

        $result = $renderer->render(
            new TableCaption('Uebersicht'),
            $page,
            160.0,
            40.0,
            20.0,
            20.0,
            160.0,
            24.0,
            'Helvetica',
            12,
            1.2,
            null,
        );

        self::assertSame($page, $result->page);
        self::assertLessThan(160.0, $result->cursorY);
        self::assertStringContainsString('(Uebersicht) Tj', $page->getContents()->render());
    }

    #[Test]
    public function it_moves_the_caption_to_a_fresh_page_when_it_no_longer_fits(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(200, 120);
        $renderer = new TableCaptionRenderer();

        $result = $renderer->render(
            new TableCaption('Uebersicht', size: 14),
            $page,
            25.0,
            20.0,
            20.0,
            20.0,
            160.0,
            18.0,
            'Helvetica',
            12,
            1.2,
            null,
        );

        self::assertNotSame($page, $result->page);
        self::assertCount(2, $document->pages->pages);
        self::assertStringContainsString('(Uebersicht) Tj', $result->page->getContents()->render());
    }
}
