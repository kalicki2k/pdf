<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathBuilderTest extends TestCase
{
    #[Test]
    public function it_strokes_a_path_with_stroke_width_color_and_opacity(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100, 100);

        $returnedPage = $page->addPath()
            ->moveTo(10, 20)
            ->lineTo(30, 40)
            ->curveTo(50, 60, 70, 80, 90, 95)
            ->close()
            ->stroke(1.5, Color::rgb(255, 0, 0), Opacity::stroke(0.25));

        self::assertSame($page, $returnedPage);
        self::assertStringContainsString("q\n1 0 0 RG\n/GS1 gs\n1.5 w\n10 20 m\n30 40 l\n50 60 70 80 90 95 c\nh\nS\nQ", $page->getContents()->render());
        self::assertStringContainsString('/ExtGState << /GS1 << /CA 0.25 >> >>', $page->getResources()->render());
    }

    #[Test]
    public function it_fills_a_path_without_stroke_settings(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100, 100);

        $page->addPath()
            ->moveTo(10, 20)
            ->lineTo(30, 40)
            ->fill(Color::gray(0.5), Opacity::fill(0.4));

        self::assertStringContainsString("q\n0.5 g\n/GS1 gs\n10 20 m\n30 40 l\nf\nQ", $page->getContents()->render());
        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 >> >>', $page->getResources()->render());
    }

    #[Test]
    public function it_fills_and_strokes_a_path(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100, 100);

        $page->addPath()
            ->moveTo(10, 20)
            ->lineTo(30, 40)
            ->fillAndStroke(2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString("q\n1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n10 20 m\n30 40 l\nB\nQ", $page->getContents()->render());
        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->getResources()->render());
    }

    #[Test]
    public function it_rejects_non_positive_stroke_widths(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path stroke width must be greater than zero.');

        $page->addPath()
            ->moveTo(10, 20)
            ->stroke(0);
    }

    #[Test]
    public function it_rejects_non_positive_stroke_widths_for_fill_and_stroke(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path stroke width must be greater than zero.');

        $page->addPath()
            ->moveTo(10, 20)
            ->fillAndStroke(0);
    }

    #[Test]
    public function it_rejects_finishing_a_path_without_drawing_commands(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path requires at least one drawing command.');

        $page->addPath()->fill();
    }

    #[Test]
    public function it_rejects_transparent_paths_for_pdf_a_1b(): void
    {
        $document = new Document(profile: Profile::pdfA1b());
        $page = $document->addPage(100, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow transparency in the current implementation.');

        $page->addPath()
            ->moveTo(10, 20)
            ->lineTo(30, 40)
            ->stroke(1.5, Color::rgb(255, 0, 0), Opacity::stroke(0.25));
    }
}
