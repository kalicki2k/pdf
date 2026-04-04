<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PageSize;
use Kalle\Pdf\Document\TextAlign;
use Kalle\Pdf\Document\TextSegment;
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
    public function it_returns_itself_when_adding_an_image_placeholder(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        self::assertSame($page, $page->addImage());
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
        self::assertStringContainsString("(abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ) Tj", $page->contents->render());
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

        $page->addParagraph('Hello', 10, 50, 100, 'Helvetica', 10, align: TextAlign::CENTER);

        self::assertStringContainsString("45 50 Td\n(Hello) Tj", $page->contents->render());
    }

    #[Test]
    public function it_right_aligns_a_paragraph_within_the_available_width(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello', 10, 50, 100, 'Helvetica', 10, align: TextAlign::RIGHT);

        self::assertStringContainsString("80 50 Td\n(Hello) Tj", $page->contents->render());
    }

    #[Test]
    public function it_justifies_automatically_wrapped_lines(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', 10, 50, 70, 'Helvetica', 10, align: TextAlign::JUSTIFY);

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", $page->contents->render());
        self::assertStringContainsString("50 50 Td\n(world) Tj", $page->contents->render());
    }

    #[Test]
    public function it_does_not_justify_lines_created_by_hard_line_breaks(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph("Hello world\nfrom PDF", 10, 50, 100, 'Helvetica', 10, align: TextAlign::JUSTIFY);

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
