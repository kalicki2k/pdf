<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Text\Input\ListOptions;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Layout\Value\BulletType;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\TextOverflow;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\TaggedPdf\StructureTag;

use function Kalle\Pdf\Tests\Support\writeDocumentToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class TextFrameTest extends TestCase
{
    #[Test]
    public function it_adds_plain_text_and_uses_default_spacing(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame->addText('Hello', 'Helvetica', 10);

        self::assertSame($page, $frame->getPage());
        self::assertStringContainsString("20 100 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertSame(88.0, $frame->getCursorY());
    }

    #[Test]
    public function it_moves_plain_text_to_a_new_page_before_and_after_rendering_when_needed(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 60);

        $frame = $page->createTextFrame(new Position(10, 20), 60, 15);
        $frame->addText('Hello', 'Helvetica', 10, spacingAfter: 8);
        $frame->addText('World', 'Helvetica', 10, spacingAfter: 12);

        self::assertCount(5, $document->pages->pages);
        self::assertStringContainsString('(Hello) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($document->pages->pages[1]->getContents()));
        self::assertStringContainsString('(World) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($document->pages->pages[3]->getContents()));
        self::assertSame($document->pages->pages[4], $frame->getPage());
        self::assertSame(20.0, $frame->getCursorY());
    }

    #[Test]
    public function it_flows_headings_and_paragraphs_using_a_shared_cursor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 220, 20);

        $frame
            ->addHeading('Headline', 'Helvetica', 20, new ParagraphOptions(structureTag: StructureTag::Heading1))
            ->addParagraph('Hello world from PDF', 'Helvetica', 10, new ParagraphOptions(structureTag: StructureTag::Paragraph, spacingAfter: 8));

        self::assertSame($page, $frame->getPage());
        self::assertStringContainsString("/H1 << /MCID 0 >> BDC\nBT\n/F1 20 Tf\n20 100 Td\n(Headline) Tj\nET\nEMC", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("/P << /MCID 1 >> BDC\nBT\n/F1 10 Tf\n20 60 Td\n(Hello world from PDF) Tj\nET\nEMC", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertSame(40.0, $frame->getCursorY());
    }

    #[Test]
    public function it_tracks_the_new_page_after_an_automatic_page_break(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 60);

        $frame = $page->createTextFrame(new Position(10, 30), 40, 15);
        $frame->addParagraph('Hello world from PDF', 'Helvetica', 10, new ParagraphOptions(spacingAfter: 6));

        self::assertCount(2, $document->pages->pages);
        self::assertSame($document->pages->pages[1], $frame->getPage());
        self::assertGreaterThanOrEqual(15.0, $frame->getCursorY());
    }

    #[Test]
    public function it_advances_to_a_new_page_when_a_paragraph_starts_at_the_bottom_margin(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 60);

        $frame = $page->createTextFrame(new Position(10, 15), 40, 15);
        $frame->addParagraph('Hello world from PDF', 'Helvetica', 10, new ParagraphOptions(spacingAfter: 6));

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertNotSame($page, $frame->getPage());
        self::assertSame(15.0, $frame->getCursorY());
    }

    #[Test]
    public function it_can_flow_text_without_structure_tags(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame
            ->addHeading('Headline', 'Helvetica', 20)
            ->addParagraph('Hello world from PDF', 'Helvetica', 10, new ParagraphOptions(spacingAfter: 8));

        self::assertStringContainsString('(Headline) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('BDC', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_forwards_opacity_for_heading_and_paragraph_content(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame
            ->addHeading('Headline', 'Helvetica', 20, new ParagraphOptions(opacity: Opacity::fill(0.5)))
            ->addParagraph('Hello world from PDF', 'Helvetica', 10, new ParagraphOptions(opacity: Opacity::fill(0.5), spacingAfter: 8));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.5 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertSame(2, substr_count(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()), '/GS1 gs'));
    }

    #[Test]
    public function it_forwards_color_for_heading_and_paragraph_content(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame
            ->addHeading('Headline', 'Helvetica', 20, new ParagraphOptions(color: Color::rgb(255, 0, 0)))
            ->addParagraph('Hello world from PDF', 'Helvetica', 10, new ParagraphOptions(color: Color::cmyk(0.1, 0.2, 0.3, 0.4), spacingAfter: 8));

        self::assertStringContainsString("1 0 0 rg\n(Headline) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("0.1 0.2 0.3 0.4 k\n(Hello world from PDF) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_accepts_text_runs_in_text_frames(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame->addParagraph(
            [
                new TextSegment('Achtung:', Color::rgb(255, 0, 0)),
                new TextSegment(' Hello world from PDF'),
            ],
            'Helvetica',
            10,
            options: new ParagraphOptions(spacingAfter: 8),
        );

        self::assertStringContainsString("1 0 0 rg\n(Achtung:) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('( Hello world from) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(PDF) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_forwards_center_alignment_from_text_frames(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 100, 20);
        $frame->addParagraph('Hello', 'Helvetica', 10, new ParagraphOptions(align: HorizontalAlign::CENTER, spacingAfter: 8));

        self::assertStringContainsString("58.61 100 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_forwards_justify_alignment_from_text_frames(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 70, 20);
        $frame->addParagraph('Hello world from PDF', 'Helvetica', 10, new ParagraphOptions(align: HorizontalAlign::JUSTIFY, spacingAfter: 8));

        self::assertStringContainsString("20 100 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("66.11 100 Td\n(world) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_limits_text_frame_paragraphs_to_max_lines_when_requested(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 40, 20);
        $frame->addParagraph(
            'Hello world from PDF',
            'Helvetica',
            10,
            options: new ParagraphOptions(maxLines: 2, overflow: TextOverflow::ELLIPSIS, spacingAfter: 8),
        );

        self::assertStringContainsString("20 100 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("20 88 Td\n(world\x85) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertSame(68.0, $frame->getCursorY());
    }

    #[Test]
    public function it_forwards_linked_text_segments_in_text_frames(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame->addParagraph(
            [
                new TextSegment('Docs', link: LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' Link', link: LinkTarget::externalUrl('https://example.com/docs')),
            ],
            'Helvetica',
            10,
            options: new ParagraphOptions(spacingAfter: 8),
        );

        self::assertStringContainsString('/Annots [8 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($frame->getPage()));
        self::assertStringContainsString('/URI (https://example.com/docs)', writeDocumentToString($document));
    }

    #[Test]
    public function it_renders_a_bullet_list_with_hanging_indent(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame->addBulletList(
            [
                'First bullet item',
                [new TextSegment('Second', bold: true), new TextSegment(' bullet item')],
            ],
            'Helvetica',
            10,
            BulletType::DASH,
            new ListOptions(
                spacingAfter: 6,
            ),
        );

        self::assertStringContainsString("20 100 Td\n(-) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("34 100 Td\n(First bullet item) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("20 84 Td\n(-) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Second) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertSame(66.0, $frame->getCursorY());
    }

    #[Test]
    public function it_renders_a_structured_list_hierarchy_for_tagged_lists(): void
    {
        $document = new Document(profile: Profile::standard(1.4), language: 'de-DE');
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame->addBulletList(
            ['First bullet item'],
            'Helvetica',
            10,
            BulletType::DASH,
            new ListOptions(structureTag: StructureTag::List),
        );

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/Lbl << /MCID 0 >> BDC', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/LBody << /MCID 1 >> BDC', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Type /StructElem /S /L ', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /LI ', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Lbl ', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /LBody ', $rendered);
        self::assertSame(1, substr_count($rendered, '/Type /StructElem /S /Lbl '));
        self::assertSame(1, substr_count($rendered, '/Type /StructElem /S /LBody '));
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Lbl \/P \d+ 0 R \/Pg \d+ 0 R \/K 0/', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/LBody \/P \d+ 0 R \/Pg \d+ 0 R \/K 1/', $rendered);
    }

    #[Test]
    public function it_uses_the_text_color_as_default_marker_color_for_bullet_lists(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame->addBulletList(
            ['First bullet item'],
            'Helvetica',
            10,
            BulletType::DISC,
            new ListOptions(color: Color::rgb(255, 0, 0)),
        );

        self::assertStringContainsString("1 0 0 rg\n(\225) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_leaves_the_frame_unchanged_for_empty_lists(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $sameFrame = $frame->addBulletList([], 'Helvetica', 10);
        $sameFrame = $sameFrame->addNumberedList([], 'Helvetica', 10);

        self::assertSame($frame, $sameFrame);
        self::assertSame($page, $frame->getPage());
        self::assertSame(100.0, $frame->getCursorY());
        self::assertStringNotContainsString('Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_moves_bullet_list_items_to_a_new_page_when_needed(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 60);

        $frame = $page->createTextFrame(new Position(10, 25), 60, 15);
        $frame->addBulletList(
            ['First item', 'Second item'],
            'Helvetica',
            10,
            BulletType::DASH,
            new ListOptions(
                spacingAfter: 4,
                itemSpacing: 4,
            ),
        );

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertStringContainsString("10 25 Td\n(-) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($document->pages->pages[1]->getContents()));
        self::assertStringContainsString('(Second) Tj', writeDocumentToString($document));
        self::assertStringContainsString('(item) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($frame->getPage()->getContents()));
    }

    #[Test]
    public function it_renders_a_numbered_list_with_custom_start_index(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);
        $frame->addNumberedList(
            [
                'First item',
                [new TextSegment('Second', bold: true), new TextSegment(' item')],
            ],
            'Helvetica',
            10,
            3,
            new ListOptions(
                spacingAfter: 6,
            ),
        );

        self::assertStringContainsString("20 100 Td\n(3.) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("34 100 Td\n(First item) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("20 84 Td\n(4.) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Second) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_numbered_lists_that_start_before_one(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 120, 20);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Numbered lists must start at 1 or greater.');

        $frame->addNumberedList(['One'], 'Helvetica', 10, 0);
    }

    #[Test]
    public function it_rejects_invalid_bullet_list_indents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 12, 20);

        try {
            $frame->addBulletList(['One'], 'Helvetica', 10, options: new ListOptions(markerIndent: 0.0));
            self::fail('Expected exception for non-positive bullet indent.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Bullet indent must be greater than zero.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bullet indent must be smaller than the text frame width.');

        $frame->addBulletList(['One'], 'Helvetica', 10, options: new ListOptions(markerIndent: 12.0));
    }

    #[Test]
    public function it_rejects_invalid_numbered_list_indents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $frame = $page->createTextFrame(new Position(20, 100), 12, 20);

        try {
            $frame->addNumberedList(['One'], 'Helvetica', 10, 1, new ListOptions(markerIndent: 0.0));
            self::fail('Expected exception for non-positive number indent.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Number indent must be greater than zero.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Number indent must be smaller than the text frame width.');

        $frame->addNumberedList(['One'], 'Helvetica', 10, 1, new ListOptions(markerIndent: 12.0));
    }

    #[Test]
    public function it_adds_spacers_and_tracks_page_breaks(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100, 60);

        $frame = $page->createTextFrame(new Position(10, 30), 60, 15);
        $frame->addSpacer(5);

        self::assertSame($page, $frame->getPage());
        self::assertSame(25.0, $frame->getCursorY());

        $frame->addSpacer(12);

        self::assertCount(2, $document->pages->pages);
        self::assertSame($document->pages->pages[1], $frame->getPage());
        self::assertSame(25.0, $frame->getCursorY());
    }
}
