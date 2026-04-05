<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\AnnotationBorderStyle;
use Kalle\Pdf\Document\ResetFormAction;
use Kalle\Pdf\Document\NamedAction;
use Kalle\Pdf\Document\LineEndingStyle;
use Kalle\Pdf\Document\GoToAction;
use Kalle\Pdf\Document\GoToRemoteAction;
use Kalle\Pdf\Document\HideAction;
use Kalle\Pdf\Document\ImportDataAction;
use Kalle\Pdf\Document\LaunchAction;
use Kalle\Pdf\Document\SetOcgStateAction;
use Kalle\Pdf\Document\SubmitFormAction;
use Kalle\Pdf\Document\ThreadAction;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\FormFieldFlags;
use Kalle\Pdf\Document\JavaScriptAction;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Document\UriAction;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Styles\BadgeStyle;
use Kalle\Pdf\Styles\CalloutStyle;
use Kalle\Pdf\Styles\PanelStyle;
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
    public function it_adds_a_file_attachment_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');
        $page = $document->addPage();
        $file = $document->getAttachment('demo.txt');

        self::assertNotNull($file);

        $result = $page->addFileAttachment(10, 20, 12, 14, $file, 'Graph', 'Anhang');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /FileAttachment', $document->render());
        self::assertStringContainsString('/FS 5 0 R', $document->render());
        self::assertStringContainsString('/Name /Graph', $document->render());
    }

    #[Test]
    public function it_adds_a_text_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addTextAnnotation(10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Text', $document->render());
        self::assertStringContainsString('/Contents (Kommentar)', $document->render());
        self::assertStringContainsString('/Name /Comment', $document->render());
        self::assertStringContainsString('/Open true', $document->render());
        self::assertStringContainsString('/T (QA)', $document->render());
    }

    #[Test]
    public function it_adds_a_popup_annotation_to_an_existing_text_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addTextAnnotation(10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);
        $annotation = $page->getAnnotations()[0];

        $result = $page->addPopupAnnotation($annotation, 30, 40, 60, 40, true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Popup', $document->render());
        self::assertStringContainsString('/Parent 7 0 R', $document->render());
        self::assertStringContainsString('/Popup 8 0 R', $document->render());
    }

    #[Test]
    public function it_adds_a_free_text_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addFreeTextAnnotation(
            10,
            20,
            80,
            24,
            'Hinweistext',
            'Helvetica',
            12,
            Color::rgb(255, 0, 0),
            Color::gray(0.5),
            Color::gray(0.9),
            'QA',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /FreeText', $document->render());
        self::assertStringContainsString('/Contents (Hinweistext)', $document->render());
        self::assertStringContainsString('/DA (/F1 12 Tf 1 0 0 rg)', $document->render());
        self::assertStringContainsString('/C [0.5]', $document->render());
        self::assertStringContainsString('/IC [0.9]', $document->render());
    }

    #[Test]
    public function it_adds_a_highlight_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addHighlightAnnotation(10, 20, 80, 12, Color::rgb(255, 255, 0), 'Markiert', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Highlight', $document->render());
        self::assertStringContainsString('/QuadPoints [10 32 90 32 10 20 90 20]', $document->render());
        self::assertStringContainsString('/C [1 1 0]', $document->render());
        self::assertStringContainsString('/Contents (Markiert)', $document->render());
    }

    #[Test]
    public function it_adds_an_underline_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addUnderlineAnnotation(10, 20, 80, 12, Color::rgb(0, 0, 255), 'Unterstrichen', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Underline', $document->render());
        self::assertStringContainsString('/QuadPoints [10 32 90 32 10 20 90 20]', $document->render());
        self::assertStringContainsString('/C [0 0 1]', $document->render());
        self::assertStringContainsString('/Contents (Unterstrichen)', $document->render());
    }

    #[Test]
    public function it_adds_a_strike_out_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addStrikeOutAnnotation(10, 20, 80, 12, Color::rgb(255, 0, 0), 'Durchgestrichen', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /StrikeOut', $document->render());
        self::assertStringContainsString('/QuadPoints [10 32 90 32 10 20 90 20]', $document->render());
        self::assertStringContainsString('/C [1 0 0]', $document->render());
        self::assertStringContainsString('/Contents (Durchgestrichen)', $document->render());
    }

    #[Test]
    public function it_adds_a_squiggly_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addSquigglyAnnotation(10, 20, 80, 12, Color::rgb(255, 0, 255), 'Wellig', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Squiggly', $document->render());
        self::assertStringContainsString('/QuadPoints [10 32 90 32 10 20 90 20]', $document->render());
        self::assertStringContainsString('/C [1 0 1]', $document->render());
        self::assertStringContainsString('/Contents (Wellig)', $document->render());
    }

    #[Test]
    public function it_adds_a_stamp_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addStampAnnotation(10, 20, 80, 24, 'Approved', Color::rgb(0, 128, 0), 'Freigegeben', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Stamp', $document->render());
        self::assertStringContainsString('/Name /Approved', $document->render());
        self::assertStringContainsString('/C [0 0.501961 0]', $document->render());
        self::assertStringContainsString('/Contents (Freigegeben)', $document->render());
    }

    #[Test]
    public function it_adds_a_square_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addSquareAnnotation(10, 20, 80, 24, Color::rgb(255, 0, 0), Color::gray(0.9), 'Kasten', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Square', $document->render());
        self::assertStringContainsString('/C [1 0 0]', $document->render());
        self::assertStringContainsString('/IC [0.9]', $document->render());
    }

    #[Test]
    public function it_adds_a_circle_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addCircleAnnotation(10, 20, 80, 24, Color::rgb(0, 0, 255), Color::gray(0.9), 'Kreis', 'QA', AnnotationBorderStyle::dashed(1.5, [2.0, 1.0]));

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Circle', $document->render());
        self::assertStringContainsString('/C [0 0 1]', $document->render());
        self::assertStringContainsString('/IC [0.9]', $document->render());
        self::assertStringContainsString('/BS << /W 1.5 /S /D /D [2 1] >>', $document->render());
    }

    #[Test]
    public function it_adds_an_ink_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addInkAnnotation(
            10,
            20,
            80,
            24,
            [
                [[10.0, 20.0], [20.0, 30.0], [30.0, 20.0]],
            ],
            Color::rgb(0, 0, 0),
            'Ink',
            'QA',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Ink', $document->render());
        self::assertStringContainsString('/InkList [[10 20 20 30 30 20]]', $document->render());
        self::assertStringContainsString('/Contents (Ink)', $document->render());
    }

    #[Test]
    public function it_adds_a_line_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addLineAnnotation(
            10,
            20,
            90,
            32,
            Color::rgb(255, 0, 0),
            'Linie',
            'QA',
            LineEndingStyle::OPEN_ARROW,
            LineEndingStyle::CLOSED_ARROW,
            'Messlinie',
            AnnotationBorderStyle::dashed(2.0, [4.0, 2.0]),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Line', $document->render());
        self::assertStringContainsString('/L [10 20 90 32]', $document->render());
        self::assertStringContainsString('/LE [/OpenArrow /ClosedArrow]', $document->render());
        self::assertStringContainsString('/Subj (Messlinie)', $document->render());
        self::assertStringContainsString('/BS << /W 2 /S /D /D [4 2] >>', $document->render());
        self::assertStringContainsString('/Contents (Linie)', $document->render());
    }

    #[Test]
    public function it_adds_a_polyline_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addPolyLineAnnotation(
            [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]],
            Color::rgb(0, 0, 255),
            'PolyLine',
            'QA',
            LineEndingStyle::CIRCLE,
            LineEndingStyle::SLASH,
            'Korrekturpfad',
            AnnotationBorderStyle::solid(2.5),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /PolyLine', $document->render());
        self::assertStringContainsString('/Vertices [10 20 40 50 90 32]', $document->render());
        self::assertStringContainsString('/LE [/Circle /Slash]', $document->render());
        self::assertStringContainsString('/Subj (Korrekturpfad)', $document->render());
        self::assertStringContainsString('/BS << /W 2.5 /S /S >>', $document->render());
        self::assertStringContainsString('/Contents (PolyLine)', $document->render());
    }

    #[Test]
    public function it_adds_a_polygon_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addPolygonAnnotation([[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(255, 0, 0), Color::gray(0.9), 'Polygon', 'QA', 'Flaechenhinweis', AnnotationBorderStyle::dashed());

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Polygon', $document->render());
        self::assertStringContainsString('/Vertices [10 20 40 50 90 32]', $document->render());
        self::assertStringContainsString('/IC [0.9]', $document->render());
        self::assertStringContainsString('/Subj (Flaechenhinweis)', $document->render());
        self::assertStringContainsString('/BS << /W 1 /S /D /D [3 2] >>', $document->render());
    }

    #[Test]
    public function it_attaches_a_popup_to_line_based_annotations(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addLineAnnotation(10, 20, 90, 32, contents: 'Linie');
        $line = $page->getAnnotations()[0];
        $page->addPopupAnnotation($line, 20, 40, 60, 40, true);

        self::assertStringContainsString('/Popup 8 0 R', $document->render());
        self::assertStringContainsString('/Subtype /Popup', $document->render());
    }

    #[Test]
    public function it_adds_a_caret_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addCaretAnnotation(10, 20, 16, 18, 'Einfuegen', 'QA', 'P');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Caret', $document->render());
        self::assertStringContainsString('/Rect [10 20 26 38]', $document->render());
        self::assertStringContainsString('/Sy /P', $document->render());
        self::assertStringContainsString('/Contents (Einfuegen)', $document->render());
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
    public function it_wraps_layered_page_content_with_optional_content_markers(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->layer('Notes', static function (\Kalle\Pdf\Document\Page $page): void {
            $page->addText('Layered', 10, 20, 'Helvetica', 12);
        });

        self::assertSame($page, $result);
        self::assertStringContainsString('/Properties << /OC1 8 0 R >>', $page->resources->render());
        self::assertStringContainsString("/OC /OC1 BDC\nq\nBT", $page->contents->render());
        self::assertStringContainsString("EMC", $page->contents->render());
    }

    #[Test]
    public function it_accepts_an_existing_layer_object_for_page_layer_content(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $layer = $document->addLayer('Notes');
        $page = $document->addPage();

        $page->layer($layer, static function (\Kalle\Pdf\Document\Page $page): void {
            $page->addText('Layered', 10, 20, 'Helvetica', 12);
        });

        self::assertStringContainsString('/Properties << /OC1 5 0 R >>', $page->resources->render());
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
    public function it_adds_a_stroked_rounded_rectangle_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addRoundedRectangle(10, 20, 100, 40, 8, 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n18 60 m", $page->contents->render());
        self::assertStringContainsString('110 52 c', $page->contents->render());
        self::assertStringContainsString("\nh\nS", $page->contents->render());
    }

    #[Test]
    public function it_fills_and_strokes_a_rounded_rectangle(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addRoundedRectangle(10, 20, 100, 40, 8, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n18 60 m", $page->contents->render());
        self::assertStringContainsString("\nh\nB", $page->contents->render());
    }

    #[Test]
    public function it_rejects_invalid_rounded_rectangle_radii(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rounded rectangle radius must not exceed half the width or height.');

        $page->addRoundedRectangle(10, 20, 100, 40, 25);
    }

    #[Test]
    public function it_adds_a_badge_with_background_and_text(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addBadge('Beta', 10, 20);

        self::assertSame($page, $result);
        self::assertStringContainsString('0.9 g', $page->contents->render());
        self::assertStringContainsString('(Beta) Tj', $page->contents->render());
    }

    #[Test]
    public function it_adds_a_badge_with_custom_style_and_link(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addBadge(
            'Aktiv',
            10,
            20,
            'Helvetica',
            12,
            new BadgeStyle(
                paddingHorizontal: 8,
                paddingVertical: 4,
                fillColor: Color::gray(0.8),
                textColor: Color::rgb(255, 0, 0),
                borderWidth: 1.5,
                borderColor: Color::rgb(0, 0, 255),
                opacity: Opacity::both(0.4),
            ),
            'https://example.com',
        );

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString("0 0 1 RG\n0.8 g\n/GS1 gs\n1.5 w", $page->contents->render());
        self::assertStringContainsString('(Aktiv) Tj', $page->contents->render());
        self::assertStringContainsString('/Annots [8 0 R]', $page->render());
    }

    #[Test]
    public function it_adds_a_badge_with_rounded_corners(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addBadge(
            'Rounded',
            10,
            20,
            'Helvetica',
            12,
            new BadgeStyle(
                cornerRadius: 4,
                fillColor: Color::gray(0.8),
                borderWidth: 1.0,
                borderColor: Color::rgb(255, 0, 0),
            ),
        );

        self::assertStringContainsString("1 0 0 RG\n0.8 g\n1 w\n14 38 m", $page->contents->render());
        self::assertStringContainsString('(Rounded) Tj', $page->contents->render());
    }

    #[Test]
    public function it_rejects_empty_badge_text(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Badge text must not be empty.');

        $page->addBadge('', 10, 20);
    }

    #[Test]
    public function it_adds_a_panel_with_title_and_body(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPanel(
            'Kurzinfo zum Stand.',
            10,
            20,
            160,
            70,
            'Hinweis',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('0.96 g', $page->contents->render());
        self::assertStringContainsString('(Hinweis) Tj', $page->contents->render());
        self::assertStringContainsString('(Kurzinfo zum Stand.) Tj', $page->contents->render());
    }

    #[Test]
    public function it_adds_a_rounded_panel_with_link(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addPanel(
            [new TextSegment('Mehr Infos unter '), new TextSegment('example.com', underline: true)],
            10,
            20,
            120,
            70,
            'Details',
            'Helvetica',
            new PanelStyle(
                cornerRadius: 8,
                fillColor: Color::gray(0.9),
                titleColor: Color::rgb(255, 0, 0),
                bodyColor: Color::gray(0.2),
                borderWidth: 1.5,
                borderColor: Color::rgb(0, 0, 1),
                opacity: Opacity::both(0.4),
            ),
            link: 'https://example.com',
        );

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString('(Details) Tj', $page->contents->render());
        self::assertStringContainsString('/Annots [8 0 R]', $page->render());
    }

    #[Test]
    public function it_rejects_panels_without_title_or_body(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Panel requires a title or body.');

        $page->addPanel('', 10, 20, 100, 40);
    }

    #[Test]
    public function it_adds_an_internal_link_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addInternalLink(10, 20, 80, 12, 'table-demo');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Annots [7 0 R]', $page->render());
        self::assertStringContainsString('/Dest /table-demo', $document->render());
    }

    #[Test]
    public function it_routes_hash_links_to_internal_destinations(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addLink(10, 20, 80, 12, '#table-demo');

        self::assertStringContainsString('/Dest /table-demo', $document->render());
    }

    #[Test]
    public function it_adds_a_callout_with_a_pointer(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addCallout(
            'Hinweis.',
            20,
            40,
            120,
            50,
            80,
            20,
            'Achtung',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('(Achtung) Tj', $page->contents->render());
        self::assertStringContainsString('(Hinweis.) Tj', $page->contents->render());
        self::assertStringContainsString("72 40 m\n88 40 l\n80 20 l\nh\nB", $page->contents->render());
    }

    #[Test]
    public function it_adds_a_styled_callout_with_link(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addCallout(
            'Interner Hinweis.',
            20,
            40,
            120,
            50,
            150,
            65,
            'Info',
            'Helvetica',
            new CalloutStyle(
                panelStyle: new PanelStyle(
                    cornerRadius: 6,
                    fillColor: Color::gray(0.9),
                    titleColor: Color::rgb(255, 0, 0),
                    borderWidth: 1.5,
                    borderColor: Color::rgb(0, 0, 1),
                    opacity: Opacity::both(0.4),
                ),
                pointerBaseWidth: 18,
            ),
            link: 'https://example.com',
        );

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', $page->resources->render());
        self::assertStringContainsString('(Info) Tj', $page->contents->render());
        self::assertStringContainsString('/Annots [8 0 R]', $page->render());
    }

    #[Test]
    public function it_adds_a_diamond_path_to_the_page_contents(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addPath()
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

        $page->addPath()
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
    public function it_adds_a_text_field_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextField('customer_name', 10, 20, 80, 12, 'Ada', 'Helvetica', 12);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Annots [9 0 R]', $page->render());
        self::assertStringContainsString('/AcroForm 8 0 R', $document->render());
        self::assertStringContainsString('/Subtype /Widget', $document->render());
        self::assertStringContainsString('/FT /Tx', $document->render());
        self::assertStringContainsString('/T (customer_name)', $document->render());
    }

    #[Test]
    public function it_adds_a_multiline_text_field_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextField('notes', 10, 20, 80, 30, "Line 1\nLine 2", 'Helvetica', 12, true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/FT /Tx', $document->render());
        self::assertStringContainsString('/T (notes)', $document->render());
        self::assertStringContainsString('/Ff 4096', $document->render());
        self::assertStringContainsString('/V (Line 1\\nLine 2)', $document->render());
    }

    #[Test]
    public function it_adds_text_field_flags_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextField(
            'secret',
            10,
            20,
            80,
            12,
            'value',
            'Helvetica',
            12,
            false,
            null,
            new FormFieldFlags(readOnly: true, required: true, password: true),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Ff 8195', $document->render());
    }

    #[Test]
    public function it_adds_a_default_value_to_the_text_field_annotation(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextField('customer_name', 10, 20, 80, 12, 'Ada', 'Helvetica', 12, defaultValue: 'Grace');

        self::assertSame($page, $result);
        self::assertStringContainsString('/V (Ada)', $document->render());
        self::assertStringContainsString('/DV (Grace)', $document->render());
    }

    #[Test]
    public function it_adds_a_checkbox_annotation_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addCheckbox('accept_terms', 10, 20, 12, true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Widget', $document->render());
        self::assertStringContainsString('/FT /Btn', $document->render());
        self::assertStringContainsString('/T (accept_terms)', $document->render());
        self::assertStringContainsString('/V /Yes', $document->render());
        self::assertStringContainsString('/AP << /N << /Off', $document->render());
    }

    #[Test]
    public function it_adds_radio_buttons_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $page->addRadioButton('delivery', 'standard', 10, 20, 12, true);
        $result = $page->addRadioButton('delivery', 'express', 30, 20, 12, false);

        self::assertSame($page, $result);
        self::assertStringContainsString('/FT /Btn', $document->render());
        self::assertStringContainsString('/T (delivery)', $document->render());
        self::assertStringContainsString('/Ff 49152', $document->render());
        self::assertStringContainsString('/V /standard', $document->render());
        self::assertStringContainsString('/AS /standard', $document->render());
        self::assertStringContainsString('/AS /Off', $document->render());
        self::assertStringContainsString('/Kids [', $document->render());
    }

    #[Test]
    public function it_adds_a_combo_box_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addComboBox(
            'country',
            10,
            20,
            80,
            12,
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            'Helvetica',
            12,
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/FT /Ch', $document->render());
        self::assertStringContainsString('/Ff 131072', $document->render());
        self::assertStringContainsString('/T (country)', $document->render());
        self::assertStringContainsString('/Opt [[(de) (Deutschland)] [(at) (Oesterreich)]]', $document->render());
        self::assertStringContainsString('/V (de)', $document->render());
    }

    #[Test]
    public function it_adds_a_default_value_to_the_combo_box_annotation(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addComboBox(
            'country',
            10,
            20,
            80,
            12,
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            'Helvetica',
            12,
            defaultValue: 'at',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/V (de)', $document->render());
        self::assertStringContainsString('/DV (at)', $document->render());
    }

    #[Test]
    public function it_adds_an_editable_combo_box_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addComboBox(
            'country',
            10,
            20,
            80,
            12,
            ['de' => 'Deutschland'],
            'de',
            'Helvetica',
            12,
            flags: new FormFieldFlags(editable: true),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Ff 393216', $document->render());
    }

    #[Test]
    public function it_rejects_invalid_combo_box_default_values(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Combo box default value must reference one of the available options.');

        $page->addComboBox(
            'country',
            10,
            20,
            80,
            12,
            ['de' => 'Deutschland'],
            'de',
            'Helvetica',
            12,
            defaultValue: 'at',
        );
    }

    #[Test]
    public function it_adds_a_list_box_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addListBox(
            'topics',
            10,
            20,
            80,
            40,
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            'forms',
            'Helvetica',
            12,
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/FT /Ch', $document->render());
        self::assertStringContainsString('/T (topics)', $document->render());
        self::assertStringContainsString('/Opt [[(pdf) (PDF)] [(forms) (Forms)] [(tables) (Tables)]]', $document->render());
        self::assertStringContainsString('/V (forms)', $document->render());
    }

    #[Test]
    public function it_adds_a_default_value_to_the_list_box_annotation(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addListBox(
            'topics',
            10,
            20,
            80,
            40,
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            'forms',
            'Helvetica',
            12,
            defaultValue: 'pdf',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/V (forms)', $document->render());
        self::assertStringContainsString('/DV (pdf)', $document->render());
    }

    #[Test]
    public function it_adds_a_multi_select_list_box_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addListBox(
            'topics',
            10,
            20,
            80,
            40,
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            ['pdf', 'forms'],
            'Helvetica',
            12,
            flags: new FormFieldFlags(multiSelect: true),
            defaultValue: ['forms', 'tables'],
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Ff 2097152', $document->render());
        self::assertStringContainsString('/V [(pdf) (forms)]', $document->render());
        self::assertStringContainsString('/DV [(forms) (tables)]', $document->render());
    }

    #[Test]
    public function it_rejects_invalid_list_box_default_values(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List box default value must reference one of the available options.');

        $page->addListBox(
            'topics',
            10,
            20,
            80,
            40,
            ['pdf' => 'PDF'],
            'pdf',
            'Helvetica',
            12,
            defaultValue: 'forms',
        );
    }

    #[Test]
    public function it_adds_a_signature_field_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $result = $page->addSignatureField('approval_signature', 10, 20, 100, 30);

        self::assertSame($page, $result);
        self::assertStringContainsString('/AcroForm 7 0 R', $document->render());
        self::assertStringContainsString('/Subtype /Widget', $document->render());
        self::assertStringContainsString('/FT /Sig', $document->render());
        self::assertStringContainsString('/T (approval_signature)', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton('save_form', 'Speichern', 10, 20, 80, 16);

        self::assertSame($page, $result);
        self::assertStringContainsString('/AcroForm 8 0 R', $document->render());
        self::assertStringContainsString('/FT /Btn', $document->render());
        self::assertStringContainsString('/Ff 65536', $document->render());
        self::assertStringContainsString('/T (save_form)', $document->render());
        self::assertStringContainsString('/CA (Speichern)', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_submit_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'save_form',
            'Speichern',
            10,
            20,
            80,
            16,
            action: new SubmitFormAction('https://example.com/submit'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /SubmitForm /F (https://example.com/submit) >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_reset_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'reset_form',
            'Zuruecksetzen',
            10,
            20,
            80,
            16,
            action: new ResetFormAction(),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /ResetForm >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_javascript_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'validate_form',
            'Pruefen',
            10,
            20,
            80,
            16,
            action: new JavaScriptAction("app.alert('Hallo');"),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString("/A << /S /JavaScript /JS (app.alert\\('Hallo'\\);) >>", $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_named_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'prev_page',
            'Zurueck',
            10,
            20,
            80,
            16,
            action: new NamedAction('PrevPage'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /Named /N /PrevPage >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_goto_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'goto_table',
            'Zur Tabelle',
            10,
            20,
            80,
            16,
            action: new GoToAction('table-demo'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /GoTo /D /table-demo >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_goto_remote_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'open_remote',
            'Extern',
            10,
            20,
            80,
            16,
            action: new GoToRemoteAction('guide.pdf', 'chapter-1'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /GoToR /F (guide.pdf) /D /chapter-1 >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_launch_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'open_file',
            'Datei',
            10,
            20,
            80,
            16,
            action: new LaunchAction('guide.pdf'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /Launch /F (guide.pdf) >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_uri_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'open_site',
            'Website',
            10,
            20,
            80,
            16,
            action: new UriAction('https://example.com'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /URI /URI (https://example.com) >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_hide_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'hide_notes',
            'Ausblenden',
            10,
            20,
            80,
            16,
            action: new HideAction('notes_panel'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /Hide /T (notes_panel) >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_an_import_data_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'import_data',
            'Import',
            10,
            20,
            80,
            16,
            action: new ImportDataAction('form-data.fdf'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /ImportData /F (form-data.fdf) >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_set_ocg_state_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $layer = $document->addLayer('LayerA');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'toggle_layer',
            'Layer',
            10,
            20,
            80,
            16,
            action: new SetOcgStateAction(['Toggle', $layer], false),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /SetOCGState /State [/Toggle 5 0 R] /PreserveRB false >>', $document->render());
    }

    #[Test]
    public function it_adds_a_push_button_with_a_thread_action_to_the_page_and_document(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'open_thread',
            'Thread',
            10,
            20,
            80,
            16,
            action: new ThreadAction('article-1', 'threads.pdf'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /Thread /D (article-1) /F (threads.pdf) >>', $document->render());
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
