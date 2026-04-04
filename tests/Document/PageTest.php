<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PageSize;
use Kalle\Pdf\Document\HorizontalAlign;
use Kalle\Pdf\Document\TextOverflow;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    #[Test]
    public function it_renders_the_page_dictionary(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage(100.0, 200.0);

        self::assertSame(
            "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 200] /Resources 6 0 R /Contents 5 0 R >>\nendobj\n",
            $page->render(),
        );
    }

    #[Test]
    public function it_renders_a_custom_page_size_helper_in_landscape(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage(PageSize::custom(100.0, 200.0)->landscape());

        self::assertSame(
            "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /Resources 6 0 R /Contents 5 0 R >>\nendobj\n",
            $page->render(),
        );
    }

    #[Test]
    public function it_adds_an_image_xobject_and_draw_command_to_the_page(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        self::assertSame($page, $page->addImage($image, 10, 20, 160, 100));
        self::assertStringContainsString('/XObject << /Im1 7 0 R >>', $page->resources->render());
        self::assertStringContainsString("160 0 0 100 10 20 cm\n/Im1 Do", $page->contents->render());
        self::assertStringContainsString("7 0 obj\n<< /Type /XObject\n/Subtype /Image", $document->render());
    }

    #[Test]
    public function it_uses_the_image_dimensions_when_no_target_size_is_given(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        $page->addImage($image, 10, 20);

        self::assertStringContainsString("320 0 0 200 10 20 cm\n/Im1 Do", $page->contents->render());
    }

    #[Test]
    public function it_rejects_non_positive_image_dimensions(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image width must be greater than zero.');

        $page->addImage($image, 10, 20, 0, 100);
    }

    #[Test]
    public function it_adds_a_line_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addLine(10, 20, 100, 20);

        self::assertSame($page, $result);
        self::assertStringContainsString("1 w\n10 20 m\n100 20 l\nS", $page->contents->render());
    }

    #[Test]
    public function it_applies_stroke_color_and_opacity_when_adding_a_line(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addLine(10, 20, 100, 20, 2.5, Color::rgb(255, 0, 0), Opacity::stroke(0.25));

        self::assertStringContainsString('/ExtGState << /GS1 << /CA 0.25 >> >>', $page->resources->render());
        self::assertStringContainsString("1 0 0 RG\n/GS1 gs\n2.5 w\n10 20 m\n100 20 l\nS", $page->contents->render());
    }

    #[Test]
    public function it_rejects_non_positive_line_widths(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line width must be greater than zero.');

        $page->addLine(10, 20, 100, 20, 0);
    }

    #[Test]
    public function it_adds_a_stroked_rectangle_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addRectangle(10, 20, 100, 40);

        self::assertSame($page, $result);
        self::assertStringContainsString("1 w\n10 20 100 40 re\nS", $page->contents->render());
    }

    #[Test]
    public function it_adds_a_filled_rectangle_without_stroking(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addRectangle(10, 20, 100, 40, null, null, Color::gray(0.5));

        self::assertStringContainsString("0.5 g\n10 20 100 40 re\nf", $page->contents->render());
    }

    #[Test]
    public function it_adds_a_filled_and_stroked_rectangle_with_opacity(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addRectangle(10, 20, 100, 40, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n10 20 100 40 re\nB", $page->contents->render());
    }

    #[Test]
    public function it_rejects_rectangles_without_stroke_or_fill(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rectangle requires either a stroke or a fill.');

        $page->addRectangle(10, 20, 100, 40, null, null, null);
    }

    #[Test]
    public function it_adds_a_diamond_path_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->path()
            ->moveTo(60, 240)
            ->lineTo(100, 200)
            ->lineTo(60, 160)
            ->lineTo(20, 200)
            ->close()
            ->stroke(1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n60 240 m\n100 200 l\n60 160 l\n20 200 l\nh\nS", $page->contents->render());
    }

    #[Test]
    public function it_fills_and_strokes_a_path(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->path()
            ->moveTo(60, 240)
            ->lineTo(100, 200)
            ->lineTo(60, 160)
            ->lineTo(20, 200)
            ->close()
            ->fillAndStroke(2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n60 240 m\n100 200 l\n60 160 l\n20 200 l\nh\nB", $page->contents->render());
    }

    #[Test]
    public function it_adds_a_stroked_circle_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addCircle(100, 100, 30, 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n100 130 m", $page->contents->render());
        self::assertStringContainsString('130 100 c', $page->contents->render());
        self::assertStringContainsString("\nh\nS", $page->contents->render());
    }

    #[Test]
    public function it_fills_and_strokes_a_circle(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addCircle(100, 100, 30, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n100 130 m", $page->contents->render());
        self::assertStringContainsString("\nh\nB", $page->contents->render());
    }

    #[Test]
    public function it_rejects_circles_without_stroke_or_fill(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circle requires either a stroke or a fill.');

        $page->addCircle(100, 100, 30, null, null, null);
    }

    #[Test]
    public function it_adds_a_stroked_ellipse_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addEllipse(100, 100, 40, 20, 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n100 120 m", $page->contents->render());
        self::assertStringContainsString('140 100 c', $page->contents->render());
        self::assertStringContainsString("\nh\nS", $page->contents->render());
    }

    #[Test]
    public function it_fills_and_strokes_an_ellipse(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addEllipse(100, 100, 40, 20, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n100 120 m", $page->contents->render());
        self::assertStringContainsString("\nh\nB", $page->contents->render());
    }

    #[Test]
    public function it_rejects_ellipses_without_stroke_or_fill(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ellipse requires either a stroke or a fill.');

        $page->addEllipse(100, 100, 40, 20, null, null, null);
    }

    #[Test]
    public function it_adds_a_stroked_polygon_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addPolygon([[60, 240], [100, 200], [60, 160], [20, 200]], 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n60 240 m\n100 200 l\n60 160 l\n20 200 l\nh\nS", $page->contents->render());
    }

    #[Test]
    public function it_rejects_polygons_with_too_few_points(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Polygon requires at least three points.');

        $page->addPolygon([[10, 10], [20, 20]]);
    }

    #[Test]
    public function it_adds_an_arrow_with_a_filled_head(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addArrow(20, 200, 100, 200, 2.0, Color::rgb(255, 0, 0), Opacity::both(0.4), 12, 10);

        self::assertSame($page, $result);
        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString("1 0 0 RG\n/GS1 gs\n2 w\n20 200 m\n88 200 l\nS", $page->contents->render());
        self::assertStringContainsString("1 0 0 rg\n/GS1 gs\n100 200 m\n88 205 l\n88 195 l\nh\nf", $page->contents->render());
    }

    #[Test]
    public function it_rejects_zero_length_arrows(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Arrow requires distinct start and end points.');

        $page->addArrow(10, 10, 10, 10);
    }

    #[Test]
    public function it_adds_a_stroked_star_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addStar(100, 100, 5, 30, 15, 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n100 70 m", $page->contents->render());
        self::assertStringContainsString("\nh\nS", $page->contents->render());
    }

    #[Test]
    public function it_fills_and_strokes_a_star(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addStar(100, 100, 5, 30, 15, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n100 70 m", $page->contents->render());
        self::assertStringContainsString("\nh\nB", $page->contents->render());
    }

    #[Test]
    public function it_rejects_stars_with_invalid_radii(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Star inner radius must be smaller than the outer radius.');

        $page->addStar(100, 100, 5, 30, 30);
    }

    #[Test]
    public function it_adds_a_link_annotation_to_the_page(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addLink(10, 20, 80, 12, 'https://example.com');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Annots [7 0 R]', $page->render());
        self::assertStringContainsString('/Subtype /Link', $document->render());
        self::assertStringContainsString('/URI (https://example.com)', $document->render());
    }

    #[Test]
    public function it_rejects_non_positive_link_dimensions(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link width must be greater than zero.');

        $page->addLink(10, 20, 0, 12, 'https://example.com');
    }

    #[Test]
    public function it_rejects_empty_link_urls(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link URL must not be empty.');

        $page->addLink(10, 20, 80, 12, '');
    }

    #[Test]
    public function it_adds_a_link_annotation_when_text_is_rendered_with_a_link(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addText('Hello', 10, 20, 'Helvetica', 12, link: 'https://example.com');

        self::assertStringContainsString('(Hello) Tj', $page->contents->render());
        self::assertStringContainsString('/Annots [8 0 R]', $page->render());
        self::assertStringContainsString('/URI (https://example.com)', $document->render());
    }

    #[Test]
    public function it_adds_link_annotations_for_linked_text_segments_in_a_paragraph(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Hello ', link: 'https://example.com'),
                new TextSegment('world', link: 'https://example.com'),
            ],
            10,
            20,
            200,
            'Helvetica',
            12,
        );

        self::assertStringContainsString('/Annots [8 0 R]', $page->render());
        self::assertSame(1, substr_count($document->render(), '/URI (https://example.com)'));
    }

    #[Test]
    public function it_creates_separate_link_annotations_for_distinct_linked_text_segments(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('One', link: 'https://one.example'),
                new TextSegment(' Two', link: 'https://two.example'),
            ],
            10,
            20,
            200,
            'Helvetica',
            12,
        );

        self::assertStringContainsString('/Annots [8 0 R 9 0 R]', $page->render());
        self::assertStringContainsString('/URI (https://one.example)', $document->render());
        self::assertStringContainsString('/URI (https://two.example)', $document->render());
    }

    #[Test]
    public function it_rejects_text_with_an_unregistered_font(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'Helvetica' is not registered.");

        $page->addText('Hello', 10, 20, 'Helvetica', 12, 'P');
    }

    #[Test]
    public function it_adds_text_to_contents_and_registers_the_font_resource(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', 10, 20, 'Helvetica', 12, 'P');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Font << /F1 4 0 R >>', $page->resources->render());
        self::assertStringContainsString('/P << /MCID 0 >> BDC', $page->contents->render());
        self::assertStringContainsString('(Hello) Tj', $page->contents->render());
        self::assertStringContainsString('10 0 obj' . "\n" . '<< /Type /StructElem /S /Document /K [11 0 R] >>', $document->render());
        self::assertStringContainsString('11 0 obj' . "\n" . '<< /Type /StructElem /S /P /P 10 0 R /Pg 5 0 R /K 0 >>', $document->render());
    }

    #[Test]
    public function it_can_add_text_without_creating_structure_metadata(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', 10, 20, 'Helvetica', 12);

        self::assertSame($page, $result);
        self::assertStringContainsString('(Hello) Tj', $page->contents->render());
        self::assertStringNotContainsString('BDC', $page->contents->render());
        self::assertStringNotContainsString('/Type /StructElem /S /P', $document->render());
    }

    #[Test]
    public function it_registers_an_extgstate_and_applies_it_when_adding_text_with_opacity(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', 10, 20, 'Helvetica', 12, null, null, Opacity::fill(0.5));

        self::assertSame($page, $result);
        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.5 >> >>', $page->resources->render());
        self::assertStringContainsString("/GS1 gs\n(Hello) Tj", $page->contents->render());
    }

    #[Test]
    public function it_applies_text_color_when_adding_text(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', 10, 20, 'Helvetica', 12, color: Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 rg\n(Hello) Tj", $page->contents->render());
    }

    #[Test]
    public function it_does_not_leak_text_color_to_following_text_without_an_explicit_color(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addText('Red', 10, 40, 'Helvetica', 12, color: Color::rgb(255, 0, 0));
        $page->addText('Default', 10, 20, 'Helvetica', 12);

        self::assertStringContainsString("1 0 0 rg\n(Red) Tj\nET\nQ\nq\nBT\n/F1 12 Tf\n10 20 Td\n(Default) Tj", $page->contents->render());
    }

    #[Test]
    public function it_rejects_text_that_is_not_supported_by_the_registered_font(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'Helvetica' does not support the provided text.");

        $page->addText('漢', 10, 20, 'Helvetica', 12, 'P');
    }

    #[Test]
    public function it_wraps_a_paragraph_into_multiple_text_lines(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addParagraph('Hello world from PDF', 10, 50, 40, 'Helvetica', 10, 'P', 12.0, 0.0);

        self::assertSame($page, $result);
        self::assertStringContainsString("10 50 Td\n/P << /MCID 0 >> BDC\n(Hello) Tj", $page->contents->render());
        self::assertStringContainsString("10 38 Td\n/P << /MCID 1 >> BDC\n(world) Tj", $page->contents->render());
        self::assertStringContainsString("10 26 Td\n/P << /MCID 2 >> BDC\n(from) Tj", $page->contents->render());
        self::assertStringContainsString("10 14 Td\n/P << /MCID 3 >> BDC\n(PDF) Tj", $page->contents->render());
    }

    #[Test]
    public function it_wraps_a_paragraph_without_creating_structure_when_no_tag_is_given(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', 10, 50, 40, 'Helvetica', 10, null, 12.0, 0.0);

        self::assertStringContainsString('(Hello) Tj', $page->contents->render());
        self::assertStringNotContainsString('BDC', $page->contents->render());
        self::assertStringNotContainsString('/Type /StructElem /S /P', $document->render());
    }

    #[Test]
    public function it_applies_opacity_to_each_line_of_a_wrapped_paragraph(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', 10, 50, 40, 'Helvetica', 10, null, 12.0, 0.0, null, Opacity::fill(0.5));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.5 >> >>', $page->resources->render());
        self::assertSame(4, substr_count($page->contents->render(), '/GS1 gs'));
    }

    #[Test]
    public function it_applies_color_to_each_line_of_a_wrapped_paragraph(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', 10, 50, 40, 'Helvetica', 10, null, 12.0, 0.0, Color::gray(0.5));

        self::assertSame(4, substr_count($page->contents->render(), '0.5 g'));
    }

    #[Test]
    public function it_clips_a_paragraph_to_the_configured_max_lines(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            'Hello world from PDF',
            10,
            50,
            40,
            'Helvetica',
            10,
            maxLines: 2,
        );

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", $page->contents->render());
        self::assertStringContainsString("10 38 Td\n(world) Tj", $page->contents->render());
        self::assertStringNotContainsString('(from) Tj', $page->contents->render());
        self::assertStringNotContainsString('(PDF) Tj', $page->contents->render());
    }

    #[Test]
    public function it_appends_an_ellipsis_when_a_paragraph_is_truncated(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            'Hello world from PDF',
            10,
            50,
            40,
            'Helvetica',
            10,
            maxLines: 2,
            overflow: TextOverflow::ELLIPSIS,
        );

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", $page->contents->render());
        self::assertStringContainsString("10 38 Td\n(wor...) Tj", $page->contents->render());
        self::assertStringNotContainsString('(from) Tj', $page->contents->render());
        self::assertStringNotContainsString('(PDF) Tj', $page->contents->render());
    }

    #[Test]
    public function it_appends_an_ellipsis_to_the_last_visible_segment_style(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Achtung:', Color::rgb(255, 0, 0)),
                new TextSegment(' Hello world from PDF', bold: true),
            ],
            10,
            50,
            55,
            'Helvetica',
            10,
            maxLines: 2,
            overflow: TextOverflow::ELLIPSIS,
        );

        self::assertStringContainsString("1 0 0 rg\n(Achtung:) Tj", $page->contents->render());
        self::assertStringContainsString("/F2 10 Tf\n10 38 Td\n(Hello...) Tj", $page->contents->render());
        self::assertStringNotContainsString('(world) Tj', $page->contents->render());
    }

    #[Test]
    public function it_can_render_mixed_style_runs_within_a_single_paragraph(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Achtung:', Color::rgb(255, 0, 0)),
                new TextSegment('abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789.:,;()*!?\'@#<>$%&^+-=~'),
            ],
            10,
            50,
            500,
            'Helvetica',
            10,
        );

        self::assertStringContainsString("1 0 0 rg\n(Achtung:) Tj", $page->contents->render());
        self::assertStringContainsString('(abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ) Tj', $page->contents->render());
        self::assertStringContainsString("(0123456789.:,;\\(\\)*!?'@#<>$%&^+-=~) Tj", $page->contents->render());
    }

    #[Test]
    public function it_wraps_paragraph_runs_across_line_breaks(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Achtung:', Color::rgb(255, 0, 0)),
                new TextSegment(' Hello world from PDF'),
            ],
            10,
            50,
            50,
            'Helvetica',
            10,
        );

        self::assertStringContainsString("10 50 Td\n1 0 0 rg\n(Achtung:) Tj", $page->contents->render());
        self::assertStringContainsString("10 38 Td\n(Hello) Tj", $page->contents->render());
        self::assertStringContainsString("10 26 Td\n(world) Tj", $page->contents->render());
    }

    #[Test]
    public function it_rejects_invalid_paragraph_run_arrays(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paragraph text arrays must contain only TextSegment instances.');

        /** @var mixed $invalidRuns */
        $invalidRuns = ['invalid'];

        $method = new \ReflectionMethod($page, 'addParagraph');
        $method->invoke($page, $invalidRuns, 10, 50, 50, 'Helvetica', 10);
    }

    #[Test]
    public function it_uses_a_bold_standard_font_variant_for_bold_segments(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [new TextSegment('Achtung', bold: true)],
            10,
            50,
            100,
            'Helvetica',
            10,
        );

        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $document->render());
    }

    #[Test]
    public function it_uses_an_italic_standard_font_variant_for_italic_segments(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Times-Roman');
        $page = $document->addPage();

        $page->addParagraph(
            [new TextSegment('Hinweis', italic: true)],
            10,
            50,
            100,
            'Times-Roman',
            10,
        );

        self::assertStringContainsString('/BaseFont /Times-Italic', $document->render());
    }

    #[Test]
    public function it_renders_underlines_and_strikethroughs_for_text_segments(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Underline', underline: true),
                new TextSegment(' Strike', strikethrough: true),
            ],
            10,
            50,
            200,
            'Helvetica',
            10,
        );

        self::assertStringContainsString('re f', $page->contents->render());
        self::assertSame(2, substr_count($page->contents->render(), 're f'));
    }

    #[Test]
    public function it_centers_a_paragraph_within_the_available_width(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello', 10, 50, 100, 'Helvetica', 10, align: HorizontalAlign::CENTER);

        self::assertStringContainsString("45 50 Td\n(Hello) Tj", $page->contents->render());
    }

    #[Test]
    public function it_right_aligns_a_paragraph_within_the_available_width(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello', 10, 50, 100, 'Helvetica', 10, align: HorizontalAlign::RIGHT);

        self::assertStringContainsString("80 50 Td\n(Hello) Tj", $page->contents->render());
    }

    #[Test]
    public function it_justifies_automatically_wrapped_lines(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', 10, 50, 70, 'Helvetica', 10, align: HorizontalAlign::JUSTIFY);

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", $page->contents->render());
        self::assertStringContainsString("50 50 Td\n(world) Tj", $page->contents->render());
    }

    #[Test]
    public function it_does_not_justify_lines_created_by_hard_line_breaks(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph("Hello world\nfrom PDF", 10, 50, 100, 'Helvetica', 10, align: HorizontalAlign::JUSTIFY);

        self::assertStringContainsString("10 50 Td\n(Hello world) Tj", $page->contents->render());
        self::assertStringContainsString("10 38 Td\n(from PDF) Tj", $page->contents->render());
    }

    #[Test]
    public function it_creates_a_follow_up_page_when_a_paragraph_reaches_the_bottom_margin(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $firstPage = $document->addPage(100.0, 60.0);

        $lastPage = $firstPage->addParagraph(
            'Hello world from PDF',
            10,
            30,
            40,
            'Helvetica',
            10,
            'P',
            12.0,
            15.0,
        );

        self::assertCount(2, $document->pages->pages);
        self::assertSame($document->pages->pages[1], $lastPage);
        self::assertStringContainsString('(Hello)', $firstPage->contents->render());
        self::assertStringContainsString('(world)', $firstPage->contents->render());
        self::assertStringContainsString('(from)', $lastPage->contents->render());
        self::assertStringContainsString('(PDF)', $lastPage->contents->render());
    }
}
