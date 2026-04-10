<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document\Attachment\EmbeddedFileStream;
use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Annotation\CaretAnnotation;
use Kalle\Pdf\Page\Annotation\CircleAnnotation;
use Kalle\Pdf\Page\Annotation\FreeTextAnnotation;
use Kalle\Pdf\Page\Annotation\HighlightAnnotation;
use Kalle\Pdf\Page\Annotation\InkAnnotation;
use Kalle\Pdf\Page\Annotation\LineAnnotation;
use Kalle\Pdf\Page\Annotation\PageAnnotationFactory;
use Kalle\Pdf\Page\Annotation\PageAnnotationFactoryContext;
use Kalle\Pdf\Page\Annotation\PolygonAnnotation;
use Kalle\Pdf\Page\Annotation\PolyLineAnnotation;
use Kalle\Pdf\Page\Annotation\PopupAnnotation;
use Kalle\Pdf\Page\Annotation\SquareAnnotation;
use Kalle\Pdf\Page\Annotation\SquigglyAnnotation;
use Kalle\Pdf\Page\Annotation\StampAnnotation;
use Kalle\Pdf\Page\Annotation\StrikeOutAnnotation;
use Kalle\Pdf\Page\Annotation\TextAnnotation;
use Kalle\Pdf\Page\Annotation\UnderlineAnnotation;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\TaggedPdf\StructureTag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageAnnotationFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_free_text_annotation_and_registers_the_resolved_font(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            'Hinweistext',
            StandardFontName::HELVETICA,
            12,
            null,
            null,
            null,
            'QA',
        );

        self::assertInstanceOf(FreeTextAnnotation::class, $annotation);
        self::assertSame([StandardFontName::HELVETICA], $resolvedFonts);
        self::assertSame([StandardFontName::HELVETICA], $registeredFonts);
        self::assertStringContainsString('/DA (/F1 12 Tf 0 g)', $annotation->render());
    }

    #[Test]
    public function it_adds_a_pdf_a_appearance_stream_to_free_text_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            'Hinweistext',
            StandardFontName::HELVETICA,
            12,
            null,
            null,
            null,
            'QA',
        );

        self::assertInstanceOf(FreeTextAnnotation::class, $annotation);
        self::assertSame([StandardFontName::HELVETICA], $resolvedFonts);
        self::assertSame([StandardFontName::HELVETICA], $registeredFonts);
        self::assertStringContainsString('/F 4', $annotation->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_binds_text_annotations_to_structure_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createTextAnnotation(new Rect(10, 20, 80, 12), 'Kommentar', 'QA', 'Note', false);

        self::assertStringContainsString('/StructParent 1', $annotation->render());
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Annot \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Kommentar\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $document->render());
    }

    #[Test]
    public function it_rejects_unbound_link_annotations_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 currently requires link annotations to be bound to tagged Link content.');

        $factory->createLinkAnnotation(new Rect(10, 20, 80, 12), LinkTarget::externalUrl('https://example.com'));
    }

    #[Test]
    public function it_allows_bound_link_annotations_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);
        $linkStructElem = $document->createStructElem(StructureTag::Link, 0, $page);

        $annotation = $factory->createLinkAnnotation(
            new Rect(10, 20, 80, 12),
            LinkTarget::externalUrl('https://example.com'),
            $linkStructElem,
            'Example',
        );

        self::assertStringContainsString('/StructParent 1', $annotation->render());
        self::assertStringContainsString('/Contents (Example)', $annotation->render());
        self::assertStringContainsString('/K [0 << /Type /OBJR /Obj', $linkStructElem->render());
        self::assertStringContainsString('/Alt (Example)', $linkStructElem->render());
    }

    #[Test]
    public function it_links_a_popup_to_a_parent_annotation_when_supported(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);
        $parent = $factory->createHighlightAnnotation(new Rect(10, 20, 80, 12), null, 'Markiert', 'QA');

        $popup = $factory->createPopupAnnotation($parent, new Rect(20, 40, 60, 30), true);

        self::assertInstanceOf(PopupAnnotation::class, $popup);
        self::assertSame([$popup], $parent->getRelatedObjects());
    }

    #[Test]
    public function it_links_a_popup_to_a_parent_annotation_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);
        $parent = $factory->createTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA', 'Comment', false);

        $popup = $factory->createPopupAnnotation($parent, new Rect(20, 40, 60, 30), true);

        self::assertInstanceOf(PopupAnnotation::class, $popup);
        self::assertSame([$popup], $parent->getRelatedObjects());
    }

    #[Test]
    public function it_adds_a_pdf_a_appearance_stream_to_highlight_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createHighlightAnnotation(new Rect(10, 20, 80, 12), null, 'Markiert', 'QA');

        self::assertInstanceOf(HighlightAnnotation::class, $annotation);
        self::assertStringContainsString('/F 4', $annotation->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_adds_a_pdf_a_appearance_stream_to_underline_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createUnderlineAnnotation(new Rect(10, 20, 80, 12), null, 'Unterstrichen', 'QA');

        self::assertInstanceOf(UnderlineAnnotation::class, $annotation);
        self::assertStringContainsString('/F 4', $annotation->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_adds_a_pdf_a_appearance_stream_to_strike_out_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createStrikeOutAnnotation(new Rect(10, 20, 80, 12), null, 'Durchgestrichen', 'QA');

        self::assertInstanceOf(StrikeOutAnnotation::class, $annotation);
        self::assertStringContainsString('/F 4', $annotation->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_adds_a_pdf_a_appearance_stream_to_squiggly_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createSquigglyAnnotation(new Rect(10, 20, 80, 12), null, 'Wellig', 'QA');

        self::assertInstanceOf(SquigglyAnnotation::class, $annotation);
        self::assertStringContainsString('/F 4', $annotation->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_adds_pdf_a_appearance_streams_to_remaining_rect_based_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $stamp = $factory->createStampAnnotation(new Rect(10, 20, 80, 24), 'Approved', null, 'Freigegeben', 'QA');
        $square = $factory->createSquareAnnotation(new Rect(10, 20, 80, 24), null, null, 'Kasten', 'QA', null);
        $circle = $factory->createCircleAnnotation(new Rect(10, 20, 80, 24), null, null, 'Kreis', 'QA', null);
        $ink = $factory->createInkAnnotation(new Rect(10, 20, 80, 24), [[[10.0, 20.0], [20.0, 30.0]]], null, 'Ink', 'QA');
        $caret = $factory->createCaretAnnotation(new Rect(10, 20, 16, 18), 'Einfuegen', 'QA', 'P');

        self::assertInstanceOf(StampAnnotation::class, $stamp);
        self::assertStringContainsString('/F 4', $stamp->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $stamp->render());
        self::assertCount(1, $stamp->getRelatedObjects());

        self::assertInstanceOf(SquareAnnotation::class, $square);
        self::assertStringContainsString('/F 4', $square->render());
        self::assertStringContainsString('/AP << /N 103 0 R >>', $square->render());
        self::assertCount(1, $square->getRelatedObjects());

        self::assertInstanceOf(CircleAnnotation::class, $circle);
        self::assertStringContainsString('/F 4', $circle->render());
        self::assertStringContainsString('/AP << /N 105 0 R >>', $circle->render());
        self::assertCount(1, $circle->getRelatedObjects());

        self::assertInstanceOf(InkAnnotation::class, $ink);
        self::assertStringContainsString('/F 4', $ink->render());
        self::assertStringContainsString('/AP << /N 107 0 R >>', $ink->render());
        self::assertCount(1, $ink->getRelatedObjects());

        self::assertInstanceOf(CaretAnnotation::class, $caret);
        self::assertStringContainsString('/F 4', $caret->render());
        self::assertStringContainsString('/AP << /N 109 0 R >>', $caret->render());
        self::assertCount(1, $caret->getRelatedObjects());
    }

    #[Test]
    public function it_adds_pdf_a_appearance_streams_to_remaining_geometric_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $line = $factory->createLineAnnotation(new Position(10, 20), new Position(90, 32), null, 'Linie', 'QA', null, null, null, null);
        $polyLine = $factory->createPolyLineAnnotation([[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], null, 'PolyLine', 'QA', null, null, null, null);
        $polygon = $factory->createPolygonAnnotation([[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], null, null, 'Polygon', 'QA', null, null);

        self::assertInstanceOf(LineAnnotation::class, $line);
        self::assertStringContainsString('/F 4', $line->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $line->render());
        self::assertCount(1, $line->getRelatedObjects());

        self::assertInstanceOf(PolyLineAnnotation::class, $polyLine);
        self::assertStringContainsString('/F 4', $polyLine->render());
        self::assertStringContainsString('/AP << /N 103 0 R >>', $polyLine->render());
        self::assertCount(1, $polyLine->getRelatedObjects());

        self::assertInstanceOf(PolygonAnnotation::class, $polygon);
        self::assertStringContainsString('/F 4', $polygon->render());
        self::assertStringContainsString('/AP << /N 105 0 R >>', $polygon->render());
        self::assertCount(1, $polygon->getRelatedObjects());
    }

    #[Test]
    public function it_rejects_rectangles_with_non_positive_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Highlight annotation width must be greater than zero.');

        $factory->createHighlightAnnotation(new Rect(10, 20, 0, 12), null, null, null);
    }

    #[Test]
    public function it_rejects_rectangles_with_non_positive_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Highlight annotation height must be greater than zero.');

        $factory->createHighlightAnnotation(new Rect(10, 20, 80, 0), null, null, null);
    }

    #[Test]
    public function it_rejects_an_empty_file_attachment_icon(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);
        $file = new FileSpecification(8, 'demo.txt', new EmbeddedFileStream(7, 'hello'), 'Demo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File attachment icon must not be empty.');

        $factory->createFileAttachmentAnnotation(new Rect(10, 20, 12, 14), $file, '', null);
    }

    #[Test]
    public function it_rejects_file_attachment_annotations_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);
        $file = new FileSpecification(8, 'demo.txt', new EmbeddedFileStream(7, 'hello'), 'Demo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow embedded file attachments.');

        $factory->createFileAttachmentAnnotation(new Rect(10, 20, 12, 14), $file, 'Graph', null);
    }

    #[Test]
    public function it_rejects_empty_text_annotation_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text annotation contents must not be empty.');

        $factory->createTextAnnotation(new Rect(10, 20, 16, 18), '', 'QA', 'Comment', true);
    }

    #[Test]
    public function it_rejects_empty_text_annotation_icons(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text annotation icon must not be empty.');

        $factory->createTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA', '', true);
    }

    #[Test]
    public function it_adds_a_pdf_a_appearance_stream_to_text_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA', 'Comment', true);

        self::assertInstanceOf(TextAnnotation::class, $annotation);
        self::assertStringContainsString('/F 4', $annotation->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_rejects_empty_free_text_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Free text annotation contents must not be empty.');

        $factory->createFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            '',
            StandardFontName::HELVETICA,
            12,
            null,
            null,
            null,
            null,
        );
    }

    #[Test]
    public function it_rejects_non_positive_free_text_font_sizes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Free text annotation font size must be greater than zero.');

        $factory->createFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            'Hinweistext',
            StandardFontName::HELVETICA,
            0,
            null,
            null,
            null,
            null,
        );
    }

    #[Test]
    public function it_rejects_empty_stamp_icons(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stamp annotation icon must not be empty.');

        $factory->createStampAnnotation(new Rect(10, 20, 80, 24), '', null, null, null);
    }

    /**
     * @param list<string> $resolvedFonts
     * @param list<string> $registeredFonts
     */
    private function createFactory(Page $page, array &$resolvedFonts, array &$registeredFonts): PageAnnotationFactory
    {
        $nextObjectId = 100;

        return new PageAnnotationFactory(
            $page,
            PageAnnotationFactoryContext::fromCallables(
                static function () use (&$nextObjectId): int {
                    return $nextObjectId++;
                },
                static function (string $baseFont) use (&$resolvedFonts): StandardFont {
                    $resolvedFonts[] = $baseFont;

                    return new StandardFont(
                        999,
                        $baseFont,
                        'Type1',
                        'WinAnsiEncoding',
                        1.4,
                    );
                },
                static function (FontDefinition $font) use (&$registeredFonts): string {
                    $registeredFonts[] = $font->getBaseFont();

                    return 'F1';
                },
            ),
        );
    }
}
