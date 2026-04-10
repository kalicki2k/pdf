<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Internal\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Internal\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Style\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CellBoxRendererTest extends TestCase
{
    #[Test]
    public function it_renders_only_the_fill_when_no_borders_are_defined(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $renderer = new CellBoxRenderer(new TableStyleResolver());

        $renderer->render($page, 10, 20, 30, 40, Color::gray(0.8), null, null, null);

        self::assertStringContainsString("0.8 g\n10 20 30 40 re\nf", $page->getContents()->render());
    }

    #[Test]
    public function it_renders_a_single_rectangle_when_all_borders_are_equivalent(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $renderer = new CellBoxRenderer(new TableStyleResolver());

        $renderer->render(
            $page,
            10,
            20,
            30,
            40,
            null,
            TableBorder::all(1.5, Color::rgb(255, 0, 0)),
            null,
            null,
        );

        self::assertStringContainsString("1 0 0 RG\n1.5 w\n10 20 30 40 re\nS", $page->getContents()->render());
    }

    #[Test]
    public function it_renders_individual_border_lines_for_non_equivalent_sides(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $renderer = new CellBoxRenderer(new TableStyleResolver());

        $renderer->render(
            $page,
            10,
            20,
            30,
            40,
            null,
            TableBorder::all(1.0, Color::gray(0.2)),
            TableBorder::vertical(2.0, Color::rgb(255, 0, 0)),
            TableBorder::only(['bottom'], 3.0, Color::rgb(0, 0, 255)),
            renderTopBorder: false,
        );

        $contents = $page->getContents()->render();

        self::assertStringNotContainsString("10 60 m\n40 60 l", $contents);
        self::assertStringContainsString("1 0 0 RG\n2 w\n40 20 m\n40 60 l\nS", $contents);
        self::assertStringContainsString("0 0 1 RG\n3 w\n10 20 m\n40 20 l\nS", $contents);
        self::assertStringContainsString("1 0 0 RG\n2 w\n10 20 m\n10 60 l\nS", $contents);
    }

    #[Test]
    public function it_renders_a_top_border_line_when_requested(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $renderer = new CellBoxRenderer(new TableStyleResolver());

        $renderer->render(
            $page,
            10,
            20,
            30,
            40,
            null,
            null,
            null,
            TableBorder::only(['top'], 2.0, Color::rgb(0, 128, 0)),
        );

        self::assertStringContainsString("0 0.501961 0 RG\n2 w\n10 60 m\n40 60 l\nS", $page->getContents()->render());
    }
}
