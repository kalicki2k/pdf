<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use InvalidArgumentException;
use Kalle\Pdf\Action\GoToAction;
use Kalle\Pdf\Action\GoToRemoteAction;
use Kalle\Pdf\Action\HideAction;
use Kalle\Pdf\Action\ImportDataAction;
use Kalle\Pdf\Action\JavaScriptAction;
use Kalle\Pdf\Action\LaunchAction;
use Kalle\Pdf\Action\NamedAction;
use Kalle\Pdf\Action\ResetFormAction;
use Kalle\Pdf\Action\SetOcgStateAction;
use Kalle\Pdf\Action\SubmitFormAction;
use Kalle\Pdf\Action\ThreadAction;
use Kalle\Pdf\Action\UriAction;
use Kalle\Pdf\Document\Attachment\EmbeddedFileStream;
use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Font\CidFont;
use Kalle\Pdf\Font\CidToGidMap;
use Kalle\Pdf\Font\FontDescriptor;
use Kalle\Pdf\Font\FontFileStream;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\ToUnicodeCMap;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Layout\Geometry\Insets;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Text\Input\FlowTextOptions;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextBoxOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\TextOverflow;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Page\Content\ImageOptions;
use Kalle\Pdf\Page\Content\PageGraphics;
use Kalle\Pdf\Page\Content\PathBuilder;
use Kalle\Pdf\Page\Content\Style\BadgeStyle;
use Kalle\Pdf\Page\Content\Style\CalloutStyle;
use Kalle\Pdf\Page\Content\Style\PanelStyle;
use Kalle\Pdf\Page\Form\FormFieldFlags;
use Kalle\Pdf\Page\Form\FormFieldLabel;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\Resources\PageFonts;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\TaggedPdf\StructureTag;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;

use function Kalle\Pdf\Tests\Support\writeDocumentToString;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use ReflectionMethod;

final class PageTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    #[Test]
    public function it_renders_the_page_dictionary(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100.0, 200.0);

        self::assertSame(
            "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 200] /Resources 6 0 R /Contents 5 0 R >>\nendobj\n",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page),
        );
    }

    #[Test]
    public function it_renders_a_custom_page_size_helper_in_landscape(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(PageSize::custom(100.0, 200.0)->landscape());

        self::assertSame(
            "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /Resources 6 0 R /Contents 5 0 R >>\nendobj\n",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page),
        );
    }

    #[Test]
    public function it_adds_an_image_xobject_and_draw_command_to_the_page(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        self::assertSame($page, $page->addImage($image, new Position(10, 20), 160, 100));
        self::assertStringContainsString('/XObject << /Im1 7 0 R >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("160 0 0 100 10 20 cm\n/Im1 Do", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("7 0 obj\n<< /Type /XObject\n/Subtype /Image", writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_tagged_figure_with_alt_text_to_the_page(): void
    {
        $document = new Document(profile: Profile::pdfA2a());
        $page = $document->addPage();
        $image = new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00");

        $page->addImage(
            $image,
            new Position(10, 20),
            30,
            40,
            new ImageOptions(structureTag: StructureTag::Figure, altText: 'Schwarzes Pixel'),
        );

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/Figure << /MCID 0 >> BDC', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Type /StructElem /S /Figure', $rendered);
        self::assertStringContainsString('/Alt (Schwarzes Pixel)', $rendered);
    }

    #[Test]
    public function it_rejects_untagged_images_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $image = new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires images to be tagged as Figure or rendered as artifacts in the current implementation.');

        $page->addImage($image, new Position(10, 20), 30, 40);
    }

    #[Test]
    public function it_rejects_figure_images_without_alt_text_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $image = new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires alt text for Figure images in the current implementation.');

        $page->addImage($image, new Position(10, 20), 30, 40, new ImageOptions(structureTag: StructureTag::Figure));
    }

    #[Test]
    public function it_adds_text_annotations_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();

        $page->addTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA');

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/Subtype /Text', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Annot \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Kommentar\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_tagged_link_annotations_for_linked_text_in_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $page->addText('Hello', new Position(10, 20), self::pdfUaRegularFont(), 12, new TextOptions(link: LinkTarget::externalUrl('https://example.com')));

        $rendered = writeDocumentToString($document);

        self::assertMatchesRegularExpression('/\/Annots \[\d+ 0 R\]/', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Contents (Hello)', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Link', $rendered);
        self::assertStringContainsString('/Alt (Hello)', $rendered);
        self::assertMatchesRegularExpression('/\/K \[0 << \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
        self::assertMatchesRegularExpression('/\/Nums \[0 \[\d+ 0 R\] 1 \d+ 0 R\]/', $rendered);
    }

    #[Test]
    public function it_nests_pdf_ua_1_text_links_inside_existing_structure_tags(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $page->addText(
            'Hello',
            new Position(10, 20),
            self::pdfUaRegularFont(),
            12,
            new TextOptions(
                structureTag: StructureTag::Paragraph,
                link: LinkTarget::externalUrl('https://example.com'),
            ),
        );

        $rendered = writeDocumentToString($document);

        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/P \/P \d+ 0 R \/K \[\d+ 0 R\]/', $rendered);
        self::assertStringContainsString('/Contents (Hello)', $rendered);
        self::assertStringContainsString('/Alt (Hello)', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Link \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Hello\) \/K \[0 << \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
        self::assertMatchesRegularExpression('/\/Nums \[0 \[\d+ 0 R\] 1 \d+ 0 R\]/', $rendered);
    }

    #[Test]
    public function it_nests_pdf_ua_1_flow_text_links_inside_existing_structure_tags(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $page->addParagraph(
            [new TextSegment('Hello', link: LinkTarget::externalUrl('https://example.com'))],
            new Position(10, 20),
            200,
            self::pdfUaRegularFont(),
            12,
            new FlowTextOptions(structureTag: StructureTag::Paragraph),
        );

        $rendered = writeDocumentToString($document);

        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/P \/P \d+ 0 R \/K \[\d+ 0 R\]/', $rendered);
        self::assertStringContainsString('/Contents (Hello)', $rendered);
        self::assertStringContainsString('/Alt (Hello)', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Link', $rendered);
        self::assertStringContainsString('/Subtype /Link', $rendered);
    }

    #[Test]
    public function it_adds_a_file_attachment_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');
        $page = $document->addPage();
        $file = $document->getAttachment('demo.txt');

        self::assertNotNull($file);

        $result = $page->addFileAttachment(new Rect(10, 20, 12, 14), $file, 'Graph', 'Anhang');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /FileAttachment', writeDocumentToString($document));
        self::assertStringContainsString('/FS 5 0 R', writeDocumentToString($document));
        self::assertStringContainsString('/Name /Graph', writeDocumentToString($document));
    }

    #[Test]
    public function it_rejects_file_attachment_annotations_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $file = new FileSpecification(
            8,
            'demo.txt',
            new EmbeddedFileStream(7, 'hello'),
            'Demo attachment',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow embedded file attachments.');

        $page->addFileAttachment(new Rect(10, 20, 12, 14), $file, 'Graph', 'Anhang');
    }

    #[Test]
    public function it_allows_file_attachment_annotations_for_pdf_a_3b(): void
    {
        $document = new Document(profile: Profile::pdfA3b());
        $document->addAttachment('data.xml', '<root/>', 'Machine-readable source', 'application/xml');
        $page = $document->addPage();
        $file = $document->getAttachment('data.xml');

        self::assertNotNull($file);

        $result = $page->addFileAttachment(new Rect(10, 20, 12, 14), $file, 'Graph', 'Anhang');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /FileAttachment', writeDocumentToString($document));
        self::assertStringContainsString('/AFRelationship /Data', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_file_attachment_annotations_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');
        $page = $document->addPage();
        $file = $document->getAttachment('demo.txt');

        self::assertNotNull($file);

        $result = $page->addFileAttachment(new Rect(10, 20, 12, 14), $file, 'Graph', 'Demo attachment');

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/Subtype /FileAttachment', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Annot \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Demo attachment\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_a_text_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA', 'Comment', true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Text', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Kommentar)', writeDocumentToString($document));
        self::assertStringContainsString('/Name /Comment', writeDocumentToString($document));
        self::assertStringContainsString('/Open true', writeDocumentToString($document));
        self::assertStringContainsString('/T (QA)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_popup_annotation_to_an_existing_text_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA', 'Comment', true);
        $annotation = $page->getAnnotations()[0];

        $result = $page->addPopupAnnotation($annotation, new Rect(30, 40, 60, 40), true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Popup', writeDocumentToString($document));
        self::assertMatchesRegularExpression('/\/Parent 7 0 R/', writeDocumentToString($document));
        self::assertMatchesRegularExpression('/\/Popup \d+ 0 R/', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_popup_annotation_to_an_existing_text_annotation_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();

        $page->addTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA', 'Comment', true);
        $annotation = $page->getAnnotations()[0];

        $result = $page->addPopupAnnotation($annotation, new Rect(30, 40, 60, 40), true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Popup', writeDocumentToString($document));
        self::assertMatchesRegularExpression('/\/Parent 7 0 R/', writeDocumentToString($document));
        self::assertMatchesRegularExpression('/\/Popup \d+ 0 R/', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_free_text_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            'Hinweistext',
            'Helvetica',
            12,
            Color::rgb(255, 0, 0),
            Color::gray(0.5),
            Color::gray(0.9),
            'QA',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /FreeText', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Hinweistext)', writeDocumentToString($document));
        self::assertStringContainsString('/DA (/F1 12 Tf 1 0 0 rg)', writeDocumentToString($document));
        self::assertStringContainsString('/C [0.5]', writeDocumentToString($document));
        self::assertStringContainsString('/IC [0.9]', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_highlight_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addHighlightAnnotation(new Rect(10, 20, 80, 12), Color::rgb(255, 255, 0), 'Markiert', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Highlight', writeDocumentToString($document));
        self::assertStringContainsString('/QuadPoints [10 32 90 32 10 20 90 20]', writeDocumentToString($document));
        self::assertStringContainsString('/C [1 1 0]', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Markiert)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_underline_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addUnderlineAnnotation(new Rect(10, 20, 80, 12), Color::rgb(0, 0, 255), 'Unterstrichen', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Underline', writeDocumentToString($document));
        self::assertStringContainsString('/QuadPoints [10 32 90 32 10 20 90 20]', writeDocumentToString($document));
        self::assertStringContainsString('/C [0 0 1]', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Unterstrichen)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_strike_out_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addStrikeOutAnnotation(new Rect(10, 20, 80, 12), Color::rgb(255, 0, 0), 'Durchgestrichen', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /StrikeOut', writeDocumentToString($document));
        self::assertStringContainsString('/QuadPoints [10 32 90 32 10 20 90 20]', writeDocumentToString($document));
        self::assertStringContainsString('/C [1 0 0]', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Durchgestrichen)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_squiggly_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addSquigglyAnnotation(new Rect(10, 20, 80, 12), Color::rgb(255, 0, 255), 'Wellig', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Squiggly', writeDocumentToString($document));
        self::assertStringContainsString('/QuadPoints [10 32 90 32 10 20 90 20]', writeDocumentToString($document));
        self::assertStringContainsString('/C [1 0 1]', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Wellig)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_stamp_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addStampAnnotation(new Rect(10, 20, 80, 24), 'Approved', Color::rgb(0, 128, 0), 'Freigegeben', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Stamp', writeDocumentToString($document));
        self::assertStringContainsString('/Name /Approved', writeDocumentToString($document));
        self::assertStringContainsString('/C [0 0.501961 0]', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Freigegeben)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_square_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addSquareAnnotation(new Rect(10, 20, 80, 24), Color::rgb(255, 0, 0), Color::gray(0.9), 'Kasten', 'QA');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Square', writeDocumentToString($document));
        self::assertStringContainsString('/C [1 0 0]', writeDocumentToString($document));
        self::assertStringContainsString('/IC [0.9]', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_circle_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addCircleAnnotation(new Rect(10, 20, 80, 24), Color::rgb(0, 0, 255), Color::gray(0.9), 'Kreis', 'QA', AnnotationBorderStyle::dashed(1.5, [2.0, 1.0]));

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Circle', writeDocumentToString($document));
        self::assertStringContainsString('/C [0 0 1]', writeDocumentToString($document));
        self::assertStringContainsString('/IC [0.9]', writeDocumentToString($document));
        self::assertStringContainsString('/BS << /W 1.5 /S /D /D [2 1] >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_ink_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addInkAnnotation(
            new Rect(10, 20, 80, 24),
            [
                [[10.0, 20.0], [20.0, 30.0], [30.0, 20.0]],
            ],
            Color::rgb(0, 0, 0),
            'Ink',
            'QA',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Ink', writeDocumentToString($document));
        self::assertStringContainsString('/InkList [[10 20 20 30 30 20]]', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Ink)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_line_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addLineAnnotation(
            new Position(10, 20),
            new Position(90, 32),
            Color::rgb(255, 0, 0),
            'Linie',
            'QA',
            LineEndingStyle::OPEN_ARROW,
            LineEndingStyle::CLOSED_ARROW,
            'Messlinie',
            AnnotationBorderStyle::dashed(2.0, [4.0, 2.0]),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Line', writeDocumentToString($document));
        self::assertStringContainsString('/L [10 20 90 32]', writeDocumentToString($document));
        self::assertStringContainsString('/LE [/OpenArrow /ClosedArrow]', writeDocumentToString($document));
        self::assertStringContainsString('/Subj (Messlinie)', writeDocumentToString($document));
        self::assertStringContainsString('/BS << /W 2 /S /D /D [4 2] >>', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Linie)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_polyline_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
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
        self::assertStringContainsString('/Subtype /PolyLine', writeDocumentToString($document));
        self::assertStringContainsString('/Vertices [10 20 40 50 90 32]', writeDocumentToString($document));
        self::assertStringContainsString('/LE [/Circle /Slash]', writeDocumentToString($document));
        self::assertStringContainsString('/Subj (Korrekturpfad)', writeDocumentToString($document));
        self::assertStringContainsString('/BS << /W 2.5 /S /S >>', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (PolyLine)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_polygon_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addPolygonAnnotation([[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(255, 0, 0), Color::gray(0.9), 'Polygon', 'QA', 'Flaechenhinweis', AnnotationBorderStyle::dashed());

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Polygon', writeDocumentToString($document));
        self::assertStringContainsString('/Vertices [10 20 40 50 90 32]', writeDocumentToString($document));
        self::assertStringContainsString('/IC [0.9]', writeDocumentToString($document));
        self::assertStringContainsString('/Subj (Flaechenhinweis)', writeDocumentToString($document));
        self::assertStringContainsString('/BS << /W 1 /S /D /D [3 2] >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_attaches_a_popup_to_line_based_annotations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addLineAnnotation(new Position(10, 20), new Position(90, 32), contents: 'Linie');
        $line = $page->getAnnotations()[0];
        $page->addPopupAnnotation($line, new Rect(20, 40, 60, 40), true);

        self::assertStringContainsString('/Popup 8 0 R', writeDocumentToString($document));
        self::assertStringContainsString('/Subtype /Popup', writeDocumentToString($document));
    }

    #[Test]
    public function it_allows_popups_for_pdf_a_2u_annotations(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();

        $page->addTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA');
        $annotation = $page->getAnnotations()[0];
        $page->addPopupAnnotation($annotation, new Rect(30, 40, 60, 40), true);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/Subtype /Text', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
        self::assertStringContainsString('/Subtype /Popup', $rendered);
        self::assertStringContainsString('/Popup ', $rendered);
        self::assertStringContainsString('/Parent ', $rendered);
    }

    #[Test]
    public function it_adds_a_caret_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addCaretAnnotation(new Rect(10, 20, 16, 18), 'Einfuegen', 'QA', 'P');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Caret', writeDocumentToString($document));
        self::assertStringContainsString('/Rect [10 20 26 38]', writeDocumentToString($document));
        self::assertStringContainsString('/Sy /P', writeDocumentToString($document));
        self::assertStringContainsString('/Contents (Einfuegen)', writeDocumentToString($document));
    }

    #[Test]
    public function it_uses_the_image_dimensions_when_no_target_size_is_given(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        $page->addImage($image, new Position(10, 20));

        self::assertStringContainsString("320 0 0 200 10 20 cm\n/Im1 Do", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_non_positive_image_dimensions(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image width must be greater than zero.');

        $page->addImage($image, new Position(10, 20), 0, 100);
    }

    #[Test]
    public function it_rejects_non_positive_image_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image height must be greater than zero.');

        $page->addImage($image, new Position(10, 20), 100, 0);
    }

    #[Test]
    public function it_rejects_images_with_non_positive_intrinsic_dimensions(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $image = new Image(0, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image dimensions must be greater than zero.');

        $page->addImage($image, new Position(10, 20));
    }

    #[Test]
    public function it_adds_a_line_to_the_page_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addLine(new Position(10, 20), new Position(100, 20));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 w\n10 20 m\n100 20 l\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_raw_lines_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires lines, shapes and paths to be rendered as artifacts in the current implementation.');

        $page->addLine(new Position(10, 20), new Position(100, 20));
    }

    #[Test]
    public function it_wraps_layered_page_content_with_optional_content_markers(): void
    {
        $document = new Document(profile: Profile::standard(1.5));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->layer('Notes', static function (Page $page): void {
            $page->addText('Layered', new Position(10, 20), 'Helvetica', 12);
        });

        self::assertSame($page, $result);
        self::assertStringContainsString('/Properties << /OC1 8 0 R >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("/OC /OC1 BDC\nq\nBT", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('EMC', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_accepts_an_existing_layer_object_for_page_layer_content(): void
    {
        $document = new Document(profile: Profile::standard(1.5));
        $document->registerFont('Helvetica');
        $layer = $document->addLayer('Notes');
        $page = $document->addPage();

        $page->layer($layer, static function (Page $page): void {
            $page->addText('Layered', new Position(10, 20), 'Helvetica', 12);
        });

        self::assertStringContainsString('/Properties << /OC1 5 0 R >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
    }

    #[Test]
    public function it_rejects_layered_page_content_for_pdf_version_1_4(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.4 does not allow optional content groups (layers). PDF 1.5 or higher is required.');

        $page->layer('Notes', static function (Page $page): void {
            $page->addText('Layered', new Position(10, 20), 'Helvetica', 12);
        });
    }

    #[Test]
    public function it_applies_stroke_color_and_opacity_when_adding_a_line(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addLine(new Position(10, 20), new Position(100, 20), 2.5, Color::rgb(255, 0, 0), Opacity::stroke(0.25));

        self::assertStringContainsString('/ExtGState << /GS1 << /CA 0.25 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("1 0 0 RG\n/GS1 gs\n2.5 w\n10 20 m\n100 20 l\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_non_positive_line_widths(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line width must be greater than zero.');

        $page->addLine(new Position(10, 20), new Position(100, 20), 0);
    }

    #[Test]
    public function it_adds_a_stroked_rectangle_to_the_page_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addRectangle(new Rect(10, 20, 100, 40));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 w\n10 20 100 40 re\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_filled_rectangle_without_stroking(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addRectangle(new Rect(10, 20, 100, 40), null, null, Color::gray(0.5));

        self::assertStringContainsString("0.5 g\n10 20 100 40 re\nf", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_filled_and_stroked_rectangle_with_opacity(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addRectangle(new Rect(10, 20, 100, 40), 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n10 20 100 40 re\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_rectangles_without_stroke_or_fill(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rectangle requires either a stroke or a fill.');

        $page->addRectangle(new Rect(10, 20, 100, 40), null, null, null);
    }

    #[Test]
    public function it_rejects_rectangles_with_non_positive_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rectangle width must be greater than zero.');

        $page->addRectangle(new Rect(10, 20, 0, 40));
    }

    #[Test]
    public function it_rejects_rectangles_with_non_positive_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rectangle height must be greater than zero.');

        $page->addRectangle(new Rect(10, 20, 100, 0));
    }

    #[Test]
    public function it_rejects_rectangles_with_non_positive_stroke_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rectangle stroke width must be greater than zero.');

        $page->addRectangle(new Rect(10, 20, 100, 40), 0);
    }

    #[Test]
    public function it_adds_a_stroked_rounded_rectangle_to_the_page_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addRoundedRectangle(new Rect(10, 20, 100, 40), 8, 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n18 60 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('110 52 c', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_fills_and_strokes_a_rounded_rectangle(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addRoundedRectangle(new Rect(10, 20, 100, 40), 8, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n18 60 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_invalid_rounded_rectangle_radii(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rounded rectangle radius must not exceed half the width or height.');

        $page->addRoundedRectangle(new Rect(10, 20, 100, 40), 25);
    }

    #[Test]
    public function it_rejects_rounded_rectangles_with_non_positive_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rounded rectangle width must be greater than zero.');

        $page->addRoundedRectangle(new Rect(10, 20, 0, 40), 8);
    }

    #[Test]
    public function it_rejects_rounded_rectangles_with_non_positive_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rounded rectangle height must be greater than zero.');

        $page->addRoundedRectangle(new Rect(10, 20, 100, 0), 8);
    }

    #[Test]
    public function it_rejects_rounded_rectangles_with_non_positive_radius(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rounded rectangle radius must be greater than zero.');

        $page->addRoundedRectangle(new Rect(10, 20, 100, 40), 0);
    }

    #[Test]
    public function it_rejects_rounded_rectangles_with_non_positive_stroke_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rounded rectangle stroke width must be greater than zero.');

        $page->addRoundedRectangle(new Rect(10, 20, 100, 40), 8, 0);
    }

    #[Test]
    public function it_rejects_rounded_rectangles_without_stroke_or_fill(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rounded rectangle requires either a stroke or a fill.');

        $page->addRoundedRectangle(new Rect(10, 20, 100, 40), 8, null, null, null);
    }

    #[Test]
    public function it_adds_a_badge_with_background_and_text(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addBadge('Beta', new Position(10, 20));

        self::assertSame($page, $result);
        self::assertStringContainsString('0.9 g', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Beta) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_badge_with_custom_style_and_link(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addBadge(
            'Aktiv',
            new Position(10, 20),
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
            LinkTarget::externalUrl('https://example.com'),
        );

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("0 0 1 RG\n0.8 g\n/GS1 gs\n1.5 w", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Aktiv) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Annots [8 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
    }

    #[Test]
    public function it_adds_a_badge_with_rounded_corners(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addBadge(
            'Rounded',
            new Position(10, 20),
            'Helvetica',
            12,
            new BadgeStyle(
                cornerRadius: 4,
                fillColor: Color::gray(0.8),
                borderWidth: 1.0,
                borderColor: Color::rgb(255, 0, 0),
            ),
        );

        self::assertStringContainsString("1 0 0 RG\n0.8 g\n1 w\n14 38 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Rounded) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_empty_badge_text(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Badge text must not be empty.');

        $page->addBadge('', new Position(10, 20));
    }

    #[Test]
    public function it_rejects_non_positive_badge_font_sizes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Badge font size must be greater than zero.');

        $page->addBadge('Beta', new Position(10, 20), size: 0);
    }

    #[Test]
    public function it_adds_a_panel_with_title_and_body(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
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
        self::assertStringContainsString('0.96 g', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Hinweis) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Kurzinfo zum Stand.) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_non_rounded_panel_via_the_rectangle_path(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addPanel(
            'Kurzinfo zum Stand.',
            10,
            20,
            160,
            70,
            'Hinweis',
            style: new PanelStyle(
                cornerRadius: 0,
                fillColor: Color::gray(0.96),
                borderColor: Color::gray(0.75),
            ),
        );

        self::assertStringContainsString("10 20 160 70 re\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_rounded_panel_with_link(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
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
            link: LinkTarget::externalUrl('https://example.com'),
        );

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString('(Details) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Annots [8 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
    }

    #[Test]
    public function it_binds_panel_links_to_visible_text_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(registerBold: true);
        $page = $document->addPage();

        $page->addPanel(
            'Kurzinfo zum Stand.',
            10,
            20,
            160,
            70,
            'Hinweis',
            self::pdfUaRegularFont(),
            link: LinkTarget::externalUrl('https://example.com'),
        );

        $rendered = writeDocumentToString($document);

        self::assertMatchesRegularExpression('/\/Annots \[\d+ 0 R \d+ 0 R\]/', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertSame(2, substr_count($rendered, '/Subtype /Link'));
        self::assertGreaterThanOrEqual(2, substr_count($rendered, '/Type /StructElem /S /Link'));
        self::assertStringContainsString('/Contents (Hinweis)', $rendered);
        self::assertStringContainsString('/Contents (Kurzinfo zum Stand.)', $rendered);
        self::assertStringContainsString('/Alt (Hinweis)', $rendered);
        self::assertStringContainsString('/Alt (Kurzinfo zum Stand.)', $rendered);
        self::assertSame(2, substr_count(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()), 'BT'));
    }

    #[Test]
    public function it_rejects_panels_without_title_or_body(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Panel requires a title or body.');

        $page->addPanel('', 10, 20, 100, 40);
    }

    #[Test]
    public function it_rejects_panels_with_non_positive_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Panel width must be greater than zero.');

        $page->addPanel('Kurzinfo', 10, 20, 0, 40);
    }

    #[Test]
    public function it_rejects_panels_with_non_positive_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Panel height must be greater than zero.');

        $page->addPanel('Kurzinfo', 10, 20, 100, 0);
    }

    #[Test]
    public function it_rejects_panels_without_positive_content_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Panel content width must be greater than zero.');

        $page->addPanel(
            'Kurzinfo',
            10,
            20,
            20,
            40,
            style: new PanelStyle(
                paddingHorizontal: 10,
            ),
        );
    }

    #[Test]
    public function it_rejects_panels_that_leave_no_space_for_body_content(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Panel height is too small for its content.');

        $page->addPanel(
            'Kurzinfo',
            10,
            20,
            100,
            20,
            'Hinweis',
            style: new PanelStyle(
                paddingVertical: 8,
                titleSize: 13,
                titleSpacing: 6,
            ),
        );
    }

    #[Test]
    public function it_adds_an_internal_link_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addInternalLink(new Rect(10, 20, 80, 12), 'table-demo');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Annots [7 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertStringContainsString('/Dest /table-demo', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_accessible_rect_link_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();

        $result = $page->addLink(new Rect(10, 20, 80, 12), 'https://example.com', 'Read more');

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/Subtype /Link', $rendered);
        self::assertStringContainsString('/Contents (Read more)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Link \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Read more\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_an_accessible_internal_rect_link_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();

        $result = $page->addInternalLink(new Rect(10, 20, 80, 12), 'chapter-1', 'Jump to chapter 1');

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/Dest /chapter-1', $rendered);
        self::assertStringContainsString('/Contents (Jump to chapter 1)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Link \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Jump to chapter 1\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_routes_hash_links_to_internal_destinations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addLink(new Rect(10, 20, 80, 12), '#table-demo');

        self::assertStringContainsString('/Dest /table-demo', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_callout_with_a_pointer(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
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
        self::assertStringContainsString('(Achtung) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Hinweis.) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("72 40 m\n88 40 l\n80 20 l\nh\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_styled_callout_with_link(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
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
            link: LinkTarget::externalUrl('https://example.com'),
        );

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString('(Info) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Annots [8 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
    }

    #[Test]
    public function it_binds_callout_links_to_visible_text_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(registerBold: true);
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
            self::pdfUaRegularFont(),
            new CalloutStyle(),
            link: LinkTarget::externalUrl('https://example.com'),
        );

        $rendered = writeDocumentToString($document);

        self::assertSame(2, substr_count($rendered, '/Subtype /Link'));
        self::assertGreaterThanOrEqual(2, substr_count($rendered, '/Type /StructElem /S /Link'));
        self::assertStringContainsString('/Contents (Info)', $rendered);
        self::assertStringContainsString('/Contents (Interner Hinweis.)', $rendered);
        self::assertStringContainsString('/Alt (Info)', $rendered);
        self::assertStringContainsString('/Alt (Interner Hinweis.)', $rendered);
        self::assertSame(2, substr_count(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()), 'BT'));
    }

    #[Test]
    public function it_adds_a_callout_with_a_bottom_pointer(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addCallout(
            'Hinweis.',
            20,
            40,
            120,
            50,
            80,
            100,
            'Achtung',
        );

        self::assertStringContainsString("72 90 m\n80 100 l\n88 90 l\nh\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_callout_with_a_left_pointer(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addCallout(
            'Hinweis.',
            20,
            40,
            120,
            50,
            10,
            65,
            'Achtung',
        );

        self::assertStringContainsString("20 57 m\n20 73 l\n10 65 l\nh\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_diamond_path_to_the_page_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addPath()
            ->moveTo(60, 240)
            ->lineTo(100, 200)
            ->lineTo(60, 160)
            ->lineTo(20, 200)
            ->close()
            ->stroke(1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n60 240 m\n100 200 l\n60 160 l\n20 200 l\nh\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_raw_paths_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires lines, shapes and paths to be rendered as artifacts in the current implementation.');

        $page->addPath()
            ->moveTo(60, 240)
            ->lineTo(100, 200)
            ->stroke();
    }

    #[Test]
    public function it_fills_and_strokes_a_path(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addPath()
            ->moveTo(60, 240)
            ->lineTo(100, 200)
            ->lineTo(60, 160)
            ->lineTo(20, 200)
            ->close()
            ->fillAndStroke(2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n60 240 m\n100 200 l\n60 160 l\n20 200 l\nh\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_adds_a_stroked_circle_to_the_page_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addCircle(100, 100, 30, 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n100 130 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('130 100 c', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_fills_and_strokes_a_circle(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addCircle(100, 100, 30, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n100 130 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_fills_a_circle_without_stroking(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addCircle(100, 100, 30, null, null, Color::gray(0.5));

        self::assertStringContainsString("0.5 g\n100 130 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nf", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_circles_without_stroke_or_fill(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circle requires either a stroke or a fill.');

        $page->addCircle(100, 100, 30, null, null, null);
    }

    #[Test]
    public function it_rejects_circles_with_non_positive_radius(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circle radius must be greater than zero.');

        $page->addCircle(100, 100, 0);
    }

    #[Test]
    public function it_rejects_circles_with_non_positive_stroke_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circle stroke width must be greater than zero.');

        $page->addCircle(100, 100, 30, 0);
    }

    #[Test]
    public function it_adds_a_stroked_ellipse_to_the_page_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addEllipse(100, 100, 40, 20, 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n100 120 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('140 100 c', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_fills_and_strokes_an_ellipse(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addEllipse(100, 100, 40, 20, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n100 120 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_ellipses_without_stroke_or_fill(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ellipse requires either a stroke or a fill.');

        $page->addEllipse(100, 100, 40, 20, null, null, null);
    }

    #[Test]
    public function it_rejects_ellipses_with_non_positive_radius_x(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ellipse radiusX must be greater than zero.');

        $page->addEllipse(100, 100, 0, 20);
    }

    #[Test]
    public function it_rejects_ellipses_with_non_positive_radius_y(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ellipse radiusY must be greater than zero.');

        $page->addEllipse(100, 100, 40, 0);
    }

    #[Test]
    public function it_rejects_ellipses_with_non_positive_stroke_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ellipse stroke width must be greater than zero.');

        $page->addEllipse(100, 100, 40, 20, 0);
    }

    #[Test]
    public function it_adds_a_stroked_polygon_to_the_page_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addPolygon([[60, 240], [100, 200], [60, 160], [20, 200]], 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n60 240 m\n100 200 l\n60 160 l\n20 200 l\nh\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_polygons_with_too_few_points(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Polygon requires at least three points.');

        $page->addPolygon([[10, 10], [20, 20]]);
    }

    #[Test]
    public function it_rejects_polygons_with_non_positive_stroke_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Polygon stroke width must be greater than zero.');

        $page->addPolygon([[60, 240], [100, 200], [60, 160]], 0);
    }

    #[Test]
    public function it_rejects_polygons_without_stroke_or_fill(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Polygon requires either a stroke or a fill.');

        $page->addPolygon([[60, 240], [100, 200], [60, 160]], null, null, null);
    }

    #[Test]
    public function it_adds_an_arrow_with_a_filled_head(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addArrow(new Position(20, 200), new Position(100, 200), 2.0, Color::rgb(255, 0, 0), Opacity::both(0.4), 12, 10);

        self::assertSame($page, $result);
        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("1 0 0 RG\n/GS1 gs\n2 w\n20 200 m\n88 200 l\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("1 0 0 rg\n/GS1 gs\n100 200 m\n88 205 l\n88 195 l\nh\nf", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_zero_length_arrows(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Arrow requires distinct start and end points.');

        $page->addArrow(new Position(10, 10), new Position(10, 10));
    }

    #[Test]
    public function it_rejects_arrows_with_non_positive_stroke_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Arrow stroke width must be greater than zero.');

        $page->addArrow(new Position(20, 200), new Position(100, 200), 0);
    }

    #[Test]
    public function it_rejects_arrows_with_non_positive_head_length(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Arrow head length must be greater than zero.');

        $page->addArrow(new Position(20, 200), new Position(100, 200), 1.0, null, null, 0);
    }

    #[Test]
    public function it_rejects_arrows_with_non_positive_head_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Arrow head width must be greater than zero.');

        $page->addArrow(new Position(20, 200), new Position(100, 200), 1.0, null, null, 10.0, 0);
    }

    #[Test]
    public function it_adds_a_stroked_star_to_the_page_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addStar(100, 100, 5, 30, 15, 1.5, Color::rgb(255, 0, 0));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 RG\n1.5 w\n100 70 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nS", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_fills_and_strokes_a_star(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addStar(100, 100, 5, 30, 15, 2.5, Color::rgb(255, 0, 0), Color::gray(0.5), Opacity::both(0.4));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("1 0 0 RG\n0.5 g\n/GS1 gs\n2.5 w\n100 70 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nB", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_stars_with_too_few_points(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Star requires at least three points.');

        $page->addStar(100, 100, 2, 30, 15);
    }

    #[Test]
    public function it_rejects_stars_with_non_positive_outer_radius(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Star outer radius must be greater than zero.');

        $page->addStar(100, 100, 5, 0, 15);
    }

    #[Test]
    public function it_rejects_stars_with_non_positive_inner_radius(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Star inner radius must be greater than zero.');

        $page->addStar(100, 100, 5, 30, 0);
    }

    #[Test]
    public function it_rejects_stars_with_invalid_radii(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Star inner radius must be smaller than the outer radius.');

        $page->addStar(100, 100, 5, 30, 30);
    }

    #[Test]
    public function it_adds_a_link_annotation_to_the_page(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addLink(new Rect(10, 20, 80, 12), 'https://example.com');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Annots [7 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertStringContainsString('/Subtype /Link', writeDocumentToString($document));
        self::assertStringContainsString('/URI (https://example.com)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_text_field_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextField('customer_name', new Rect(10, 20, 80, 12), 'Ada', 'Helvetica', 12);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Annots [9 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertStringContainsString('/AcroForm 8 0 R', writeDocumentToString($document));
        self::assertStringContainsString('/Subtype /Widget', writeDocumentToString($document));
        self::assertStringContainsString('/FT /Tx', writeDocumentToString($document));
        self::assertStringContainsString('/T (customer_name)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_multiline_text_field_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextField('notes', new Rect(10, 20, 80, 30), "Line 1\nLine 2", 'Helvetica', 12, true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/FT /Tx', writeDocumentToString($document));
        self::assertStringContainsString('/T (notes)', writeDocumentToString($document));
        self::assertStringContainsString('/Ff 4096', writeDocumentToString($document));
        self::assertStringContainsString('/V (Line 1\\nLine 2)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_text_field_flags_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextField(
            'secret',
            new Rect(10, 20, 80, 12),
            'value',
            'Helvetica',
            12,
            false,
            null,
            new FormFieldFlags(readOnly: true, required: true, password: true),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Ff 8195', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_default_value_to_the_text_field_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextField('customer_name', new Rect(10, 20, 80, 12), 'Ada', 'Helvetica', 12, defaultValue: 'Grace');

        self::assertSame($page, $result);
        self::assertStringContainsString('/V (Ada)', writeDocumentToString($document));
        self::assertStringContainsString('/DV (Grace)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_accessible_text_field_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $result = $page->addTextField('customer_name', new Rect(10, 20, 80, 12), 'Ada', self::pdfUaRegularFont(), 12, accessibleName: 'Customer name');

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/TU (Customer name)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Customer name\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_binds_visible_text_field_labels_into_the_form_structure_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $result = $page->addTextField(
            'customer_name',
            new Rect(10, 20, 80, 12),
            'Ada',
            self::pdfUaRegularFont(),
            12,
            fieldLabel: new FormFieldLabel(
                'Customer name',
                new Position(10, 38),
                self::pdfUaRegularFont(),
                10,
            ),
        );

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/TU (Customer name)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertSame(1, substr_count(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()), '/P << /MCID'));
        self::assertStringContainsString('/Type /StructElem /S /Div', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Customer name\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_a_checkbox_annotation_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addCheckbox('accept_terms', new Position(10, 20), 12, true);

        self::assertSame($page, $result);
        self::assertStringContainsString('/Subtype /Widget', writeDocumentToString($document));
        self::assertStringContainsString('/FT /Btn', writeDocumentToString($document));
        self::assertStringContainsString('/T (accept_terms)', writeDocumentToString($document));
        self::assertStringContainsString('/V /Yes', writeDocumentToString($document));
        self::assertStringContainsString('/AP << /N << /Off', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_accessible_checkbox_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $result = $page->addCheckbox('accept_terms', new Position(10, 20), 12, true, 'Accept terms');

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/TU (Accept terms)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Accept terms\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_radio_buttons_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addRadioButton('delivery', 'standard', new Position(10, 20), 12, true);
        $result = $page->addRadioButton('delivery', 'express', new Position(30, 20), 12, false);

        self::assertSame($page, $result);
        self::assertStringContainsString('/FT /Btn', writeDocumentToString($document));
        self::assertStringContainsString('/T (delivery)', writeDocumentToString($document));
        self::assertStringContainsString('/Ff 49152', writeDocumentToString($document));
        self::assertStringContainsString('/V /standard', writeDocumentToString($document));
        self::assertStringContainsString('/AS /standard', writeDocumentToString($document));
        self::assertStringContainsString('/AS /Off', writeDocumentToString($document));
        self::assertStringContainsString('/Kids [', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_accessible_radio_buttons_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $page->addRadioButton('delivery', 'standard', new Position(10, 20), 12, true, 'Standard delivery');
        $result = $page->addRadioButton('delivery', 'express', new Position(30, 20), 12, false, 'Express delivery');

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/TU (delivery)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/StructParent 2', $rendered);
        self::assertStringContainsString('/Tabs /S', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Standard delivery\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Express delivery\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_a_combo_box_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addComboBox(
            'country',
            new Rect(10, 20, 80, 12),
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            'Helvetica',
            12,
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/FT /Ch', writeDocumentToString($document));
        self::assertStringContainsString('/Ff 131072', writeDocumentToString($document));
        self::assertStringContainsString('/T (country)', writeDocumentToString($document));
        self::assertStringContainsString('/Opt [[(de) (Deutschland)] [(at) (Oesterreich)]]', writeDocumentToString($document));
        self::assertStringContainsString('/V (de)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_accessible_combo_box_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $result = $page->addComboBox(
            'country',
            new Rect(10, 20, 80, 12),
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            self::pdfUaRegularFont(),
            12,
            accessibleName: 'Country selection',
        );

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/TU (Country selection)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Country selection\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_a_default_value_to_the_combo_box_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addComboBox(
            'country',
            new Rect(10, 20, 80, 12),
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            'Helvetica',
            12,
            defaultValue: 'at',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/V (de)', writeDocumentToString($document));
        self::assertStringContainsString('/DV (at)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_editable_combo_box_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addComboBox(
            'country',
            new Rect(10, 20, 80, 12),
            ['de' => 'Deutschland'],
            'de',
            'Helvetica',
            12,
            flags: new FormFieldFlags(editable: true),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Ff 393216', writeDocumentToString($document));
    }

    #[Test]
    public function it_rejects_invalid_combo_box_default_values(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Combo box default value must reference one of the available options.');

        $page->addComboBox(
            'country',
            new Rect(10, 20, 80, 12),
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
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addListBox(
            'topics',
            new Rect(10, 20, 80, 40),
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            'forms',
            'Helvetica',
            12,
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/FT /Ch', writeDocumentToString($document));
        self::assertStringContainsString('/T (topics)', writeDocumentToString($document));
        self::assertStringContainsString('/Opt [[(pdf) (PDF)] [(forms) (Forms)] [(tables) (Tables)]]', writeDocumentToString($document));
        self::assertStringContainsString('/V (forms)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_accessible_list_box_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $result = $page->addListBox(
            'topics',
            new Rect(10, 20, 80, 40),
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            'forms',
            self::pdfUaRegularFont(),
            12,
            accessibleName: 'Topics selection',
        );

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/TU (Topics selection)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Topics selection\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_a_default_value_to_the_list_box_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addListBox(
            'topics',
            new Rect(10, 20, 80, 40),
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            'forms',
            'Helvetica',
            12,
            defaultValue: 'pdf',
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/V (forms)', writeDocumentToString($document));
        self::assertStringContainsString('/DV (pdf)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_multi_select_list_box_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addListBox(
            'topics',
            new Rect(10, 20, 80, 40),
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            ['pdf', 'forms'],
            'Helvetica',
            12,
            flags: new FormFieldFlags(multiSelect: true),
            defaultValue: ['forms', 'tables'],
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/Ff 2097152', writeDocumentToString($document));
        self::assertStringContainsString('/V [(pdf) (forms)]', writeDocumentToString($document));
        self::assertStringContainsString('/DV [(forms) (tables)]', writeDocumentToString($document));
    }

    #[Test]
    public function it_rejects_invalid_list_box_default_values(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List box default value must reference one of the available options.');

        $page->addListBox(
            'topics',
            new Rect(10, 20, 80, 40),
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
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $result = $page->addSignatureField('approval_signature', new Rect(10, 20, 100, 30));

        self::assertSame($page, $result);
        self::assertStringContainsString('/AcroForm 7 0 R', writeDocumentToString($document));
        self::assertStringContainsString('/Subtype /Widget', writeDocumentToString($document));
        self::assertStringContainsString('/FT /Sig', writeDocumentToString($document));
        self::assertStringContainsString('/T (approval_signature)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_accessible_signature_field_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $result = $page->addSignatureField('approval_signature', new Rect(10, 20, 100, 30), 'Approval signature');

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/TU (Approval signature)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Approval signature\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_a_push_button_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton('save_form', 'Speichern', new Rect(10, 20, 80, 16));

        self::assertSame($page, $result);
        self::assertStringContainsString('/AcroForm 8 0 R', writeDocumentToString($document));
        self::assertStringContainsString('/FT /Btn', writeDocumentToString($document));
        self::assertStringContainsString('/Ff 65536', writeDocumentToString($document));
        self::assertStringContainsString('/T (save_form)', writeDocumentToString($document));
        self::assertStringContainsString('/CA (Speichern)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_an_accessible_push_button_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $result = $page->addPushButton('save_form', 'Speichern', new Rect(10, 20, 80, 16), self::pdfUaRegularFont(), 12, accessibleName: 'Save form');

        self::assertSame($page, $result);

        $rendered = writeDocumentToString($document);

        self::assertStringContainsString('/TU (Save form)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Save form\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_adds_a_push_button_with_a_submit_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'save_form',
            'Speichern',
            new Rect(10, 20, 80, 16),
            action: new SubmitFormAction('https://example.com/submit'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /SubmitForm /F (https://example.com/submit) >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_reset_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'reset_form',
            'Zuruecksetzen',
            new Rect(10, 20, 80, 16),
            action: new ResetFormAction(),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /ResetForm >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_javascript_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'validate_form',
            'Pruefen',
            new Rect(10, 20, 80, 16),
            action: new JavaScriptAction("app.alert('Hallo');"),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString("/A << /S /JavaScript /JS (app.alert\\('Hallo'\\);) >>", writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_named_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'prev_page',
            'Zurueck',
            new Rect(10, 20, 80, 16),
            action: new NamedAction('PrevPage'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /Named /N /PrevPage >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_goto_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'goto_table',
            'Zur Tabelle',
            new Rect(10, 20, 80, 16),
            action: new GoToAction('table-demo'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /GoTo /D /table-demo >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_goto_remote_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'open_remote',
            'Extern',
            new Rect(10, 20, 80, 16),
            action: new GoToRemoteAction('guide.pdf', 'chapter-1'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /GoToR /F (guide.pdf) /D /chapter-1 >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_launch_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'open_file',
            'Datei',
            new Rect(10, 20, 80, 16),
            action: new LaunchAction('guide.pdf'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /Launch /F (guide.pdf) >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_uri_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'open_site',
            'Website',
            new Rect(10, 20, 80, 16),
            action: new UriAction('https://example.com'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /URI /URI (https://example.com) >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_hide_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'hide_notes',
            'Ausblenden',
            new Rect(10, 20, 80, 16),
            action: new HideAction('notes_panel'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /Hide /T (notes_panel) >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_an_import_data_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'import_data',
            'Import',
            new Rect(10, 20, 80, 16),
            action: new ImportDataAction('form-data.fdf'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /ImportData /F (form-data.fdf) >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_a_push_button_with_a_set_ocg_state_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.5));
        $document->registerFont('Helvetica');
        $layer = $document->addLayer('LayerA');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'toggle_layer',
            'Layer',
            new Rect(10, 20, 80, 16),
            action: new SetOcgStateAction(['Toggle', $layer], false),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /SetOCGState /State [/Toggle 5 0 R] /PreserveRB false >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_rejects_a_push_button_with_a_set_ocg_state_action_for_pdf_version_1_4(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $layer = new OptionalContentGroup(99, 'LayerA');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.4 does not allow optional content groups (layers). PDF 1.5 or higher is required.');

        $page->addPushButton(
            'toggle_layer',
            'Layer',
            new Rect(10, 20, 80, 16),
            action: new SetOcgStateAction(['Toggle', $layer], false),
        );
    }

    #[Test]
    public function it_adds_a_push_button_with_a_thread_action_to_the_page_and_document(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addPushButton(
            'open_thread',
            'Thread',
            new Rect(10, 20, 80, 16),
            action: new ThreadAction('article-1', 'threads.pdf'),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString('/A << /S /Thread /D (article-1) /F (threads.pdf) >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_rejects_non_positive_link_dimensions(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link width must be greater than zero.');

        $page->addLink(new Rect(10, 20, 0, 12), 'https://example.com');
    }

    #[Test]
    public function it_rejects_empty_link_urls(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link URL must not be empty.');

        $page->addLink(new Rect(10, 20, 80, 12), '');
    }

    #[Test]
    public function it_rejects_empty_internal_link_destinations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link destination must not be empty.');

        $page->addInternalLink(new Rect(10, 20, 80, 12), '');
    }

    #[Test]
    public function it_requires_accessible_names_for_standalone_links_in_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires an accessible name for standalone link annotations.');

        $page->addLink(new Rect(10, 20, 80, 12), 'https://example.com');
    }

    #[Test]
    public function it_exposes_the_owning_document_and_current_annotations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $page->addLink(new Rect(10, 20, 80, 12), 'https://example.com');

        self::assertSame($document, $page->getDocument());
        self::assertCount(1, $page->getAnnotations());
        self::assertStringContainsString('/Subtype /Link', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getAnnotations()[0]));
    }

    #[Test]
    public function it_rejects_non_positive_text_sizes_when_measuring_text_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text size must be greater than zero.');

        $page->measureTextWidth('Hello', 'Helvetica', 0);
    }

    #[Test]
    public function it_skips_unicode_width_updates_when_the_cid_to_gid_map_is_missing(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $fonts = PageFonts::forPage($page);
        $glyphMap = new UnicodeGlyphMap();
        $font = new UnicodeFont(
            12,
            new CidFont(
                13,
                'TestUnicode',
                fontDescriptor: new FontDescriptor(14, 'TestUnicode', new FontFileStream(15, 'FONTDATA')),
            ),
            new ToUnicodeCMap(16, $glyphMap),
            $glyphMap,
        );

        $method = new ReflectionMethod($fonts, 'updateUnicodeFontWidths');

        self::assertNull($method->invoke($fonts, $font));
    }

    #[Test]
    public function it_updates_unicode_font_widths_when_all_required_font_data_is_available(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $fonts = PageFonts::forPage($page);
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');
        $fontData = file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf');

        if ($fontData === false) {
            self::fail('Unable to read assets/fonts/NotoSansCJKsc-Regular.otf.');
        }

        $fontParser = new OpenTypeFontParser($fontData);
        $font = new UnicodeFont(
            12,
            new CidFont(
                13,
                'NotoSansCJKsc-Regular',
                fontDescriptor: new FontDescriptor(
                    14,
                    'NotoSansCJKsc-Regular',
                    new FontFileStream(15, $fontData, 'FontFile3', 'OpenType'),
                ),
                cidToGidMap: new CidToGidMap(16, $glyphMap, $fontParser),
            ),
            new ToUnicodeCMap(17, $glyphMap),
            $glyphMap,
        );

        $fonts->updateUnicodeFontWidths($font);

        $renderedCidFont = \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($font->descendantFont);

        self::assertStringContainsString('/W [1 [1000] 2 [1000]]', $renderedCidFont);
    }

    #[Test]
    public function it_adds_a_link_annotation_when_text_is_rendered_with_a_link(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addText('Hello', new Position(10, 20), 'Helvetica', 12, new TextOptions(link: LinkTarget::externalUrl('https://example.com')));

        self::assertStringContainsString('(Hello) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Annots [8 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertStringContainsString('/URI (https://example.com)', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_link_annotations_for_linked_text_segments_in_a_paragraph(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Hello ', link: LinkTarget::externalUrl('https://example.com')),
                new TextSegment('world', link: LinkTarget::externalUrl('https://example.com')),
            ],
            new Position(10, 20),
            200,
            'Helvetica',
            12,
        );

        self::assertStringContainsString('/Annots [8 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertSame(1, substr_count(writeDocumentToString($document), '/URI (https://example.com)'));
    }

    #[Test]
    public function it_creates_separate_link_annotations_for_distinct_linked_text_segments(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('One', link: LinkTarget::externalUrl('https://one.example')),
                new TextSegment(' Two', link: LinkTarget::externalUrl('https://two.example')),
            ],
            new Position(10, 20),
            200,
            'Helvetica',
            12,
        );

        self::assertStringContainsString('/Annots [8 0 R 9 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertStringContainsString('/URI (https://one.example)', writeDocumentToString($document));
        self::assertStringContainsString('/URI (https://two.example)', writeDocumentToString($document));
    }

    #[Test]
    public function it_rejects_text_with_an_unregistered_font(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'Helvetica' is not registered.");

        $page->addText('Hello', new Position(10, 20), 'Helvetica', 12, new TextOptions(structureTag: StructureTag::Paragraph));
    }

    #[Test]
    public function it_reports_pdf_a_2u_font_requirements_for_unregistered_text_fonts(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Profile PDF/A-2u requires embedded Unicode fonts in the current implementation. Font 'Helvetica' is not registered.");

        $page->addText('Hallo', new Position(10, 20), 'Helvetica', 12);
    }

    #[Test]
    public function it_reports_pdf_a_2b_font_requirements_for_unregistered_text_fonts(): void
    {
        $document = new Document(profile: Profile::pdfA2b());
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Profile PDF/A-2b requires embedded Unicode fonts in the current implementation. Font 'Helvetica' is not registered.");

        $page->addText('Hallo', new Position(10, 20), 'Helvetica', 12);
    }

    #[Test]
    public function it_reports_pdf_a_3b_font_requirements_for_unregistered_text_fonts(): void
    {
        $document = new Document(profile: Profile::pdfA3b());
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Profile PDF/A-3b requires embedded Unicode fonts in the current implementation. Font 'Helvetica' is not registered.");

        $page->addText('Hallo', new Position(10, 20), 'Helvetica', 12);
    }

    #[Test]
    public function it_reports_pdf_ua_1_font_requirements_for_unregistered_text_fonts(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Profile PDF/UA-1 requires embedded Unicode fonts in the current implementation. Font 'Helvetica' is not registered.");

        $page->addText('Hallo', new Position(10, 20), 'Helvetica', 12, new TextOptions(structureTag: StructureTag::Paragraph));
    }

    #[Test]
    public function it_adds_text_to_contents_and_registers_the_font_resource(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', new Position(10, 20), 'Helvetica', 12, new TextOptions(structureTag: StructureTag::Paragraph));

        self::assertSame($page, $result);
        self::assertStringContainsString('/Font << /F1 4 0 R >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("/P << /MCID 0 >> BDC\nBT", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(Hello) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('10 0 obj' . "\n" . '<< /Type /StructElem /S /Document /P 8 0 R /K [11 0 R] >>', writeDocumentToString($document));
        self::assertStringContainsString('11 0 obj' . "\n" . '<< /Type /StructElem /S /P /P 10 0 R /Pg 5 0 R /K 0 >>', writeDocumentToString($document));
    }

    #[Test]
    public function it_marks_panel_backgrounds_as_artifacts_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(registerBold: true);
        $page = $document->addPage();

        $page->addPanel(
            'Body',
            10,
            120,
            80,
            55,
            'Title',
            self::pdfUaRegularFont(),
            new PanelStyle(),
        );

        $rendered = \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents());

        self::assertStringContainsString('/Artifact BMC', $rendered);
        self::assertStringContainsString('EMC', $rendered);
    }

    #[Test]
    public function it_tags_badge_and_panel_text_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(registerBold: true);
        $page = $document->addPage();

        $page->addBadge('Beta', new Position(10, 200), self::pdfUaRegularFont(), 10);
        $page->addPanel(
            'Body',
            10,
            130,
            80,
            55,
            'Title',
            self::pdfUaRegularFont(),
            new PanelStyle(),
        );

        self::assertGreaterThanOrEqual(3, substr_count(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()), '/P << /MCID'));
    }

    #[Test]
    public function it_can_add_text_without_creating_structure_metadata(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', new Position(10, 20), 'Helvetica', 12);

        self::assertSame($page, $result);
        self::assertStringContainsString('(Hello) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('BDC', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('/Type /StructElem /S /P', writeDocumentToString($document));
    }

    #[Test]
    public function it_accepts_text_options_for_styling_and_links(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText(
            'Hello',
            new Position(10, 20),
            options: new TextOptions(
                color: Color::rgb(255, 0, 0),
                opacity: Opacity::fill(0.5),
                underline: true,
                strikethrough: true,
                link: LinkTarget::externalUrl('https://example.com'),
            ),
        );

        self::assertSame($page, $result);
        self::assertStringContainsString("/F1 12 Tf\n10 20 Td", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("1 0 0 rg\n/GS1 gs\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString(' re f', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Annots [8 0 R]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page));
        self::assertStringContainsString('/URI (https://example.com)', writeDocumentToString($document));
    }

    #[Test]
    public function it_accepts_text_options_for_structure_tags(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addText('Hello', new Position(10, 20), options: new TextOptions(structureTag: StructureTag::Paragraph));

        self::assertStringContainsString('/P << /MCID 0 >> BDC', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Type /StructElem /S /P', writeDocumentToString($document));
    }

    #[Test]
    public function it_registers_an_extgstate_and_applies_it_when_adding_text_with_opacity(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', new Position(10, 20), 'Helvetica', 12, new TextOptions(opacity: Opacity::fill(0.5)));

        self::assertSame($page, $result);
        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.5 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertStringContainsString("/GS1 gs\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_applies_text_color_when_adding_text(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', new Position(10, 20), 'Helvetica', 12, new TextOptions(color: Color::rgb(255, 0, 0)));

        self::assertSame($page, $result);
        self::assertStringContainsString("1 0 0 rg\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_does_not_leak_text_color_to_following_text_without_an_explicit_color(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addText('Red', new Position(10, 40), 'Helvetica', 12, new TextOptions(color: Color::rgb(255, 0, 0)));
        $page->addText('Default', new Position(10, 20), 'Helvetica', 12);

        self::assertStringContainsString("1 0 0 rg\n(Red) Tj\nET\nQ\nq\nBT\n/F1 12 Tf\n10 20 Td\n(Default) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_text_that_is_not_supported_by_the_registered_font(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'Helvetica' does not support the provided text.");

        $page->addText('漢', new Position(10, 20), 'Helvetica', 12, new TextOptions(structureTag: StructureTag::Paragraph));
    }

    #[Test]
    public function it_renders_german_sharp_s_with_helvetica_in_pdf_1_0(): void
    {
        $document = new Document(profile: Profile::standard(1.0));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addText('Straße', new Position(10, 20), 'Helvetica', 12);

        self::assertStringContainsString("(Stra\xA7e) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/BaseEncoding /StandardEncoding', writeDocumentToString($document));
    }

    #[Test]
    public function it_renders_german_umlauts_and_western_accents_with_helvetica_in_pdf_1_0(): void
    {
        $document = new Document(profile: Profile::standard(1.0));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addText('ÄäÖöÜüßàáçèé', new Position(10, 20), 'Helvetica', 12);

        self::assertStringContainsString("(\x80\x8A\x85\x9A\x86\x9F\xA7\x88\x87\x8D\x8F\x8E) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('/Adieresis', writeDocumentToString($document));
        self::assertStringContainsString('/adieresis', writeDocumentToString($document));
        self::assertStringContainsString('/Odieresis', writeDocumentToString($document));
        self::assertStringContainsString('/udieresis', writeDocumentToString($document));
        self::assertStringContainsString('/germandbls', writeDocumentToString($document));
    }

    #[Test]
    public function it_renders_the_complete_supported_western_standard_font_set_in_pdf_1_0(): void
    {
        $document = new Document(profile: Profile::standard(1.0));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $text = 'ÄÅÇÉÑÖÜáàâäãåçéèêëíìîïñóòôöõúùûü†°¢£§•¶ß®©™´¨ÆØ±¥µªºæø';
        $page->addText($text, new Position(10, 20), 'Helvetica', 12);

        self::assertStringContainsString(
            "(\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F\xA0\xA1\xA2\xA3\xA4\xA5\xA6\xA7\xA8\xA9\xAA\xAB\xAC\xAE\xAF\xB1\xB4\xB5\xBB\xBC\xBE\xBF) Tj",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()),
        );
    }

    #[Test]
    public function it_renders_the_expected_win_ansi_matrix_with_helvetica_in_pdf_1_4(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $text = 'ÄÖÜäöüßàáâãåçèéêëíìîïñóòôõúùûü€ŒœŠšŽžŸ„“”‘’…–—•™';
        $page->addText($text, new Position(10, 20), 'Helvetica', 12);

        self::assertStringContainsString(
            "(\xC4\xD6\xDC\xE4\xF6\xFC\xDF\xE0\xE1\xE2\xE3\xE5\xE7\xE8\xE9\xEA\xEB\xED\xEC\xEE\xEF\xF1\xF3\xF2\xF4\xF5\xFA\xF9\xFB\xFC\x80\x8C\x9C\x8A\x9A\x8E\x9E\x9F\x84\x93\x94\x91\x92\x85\x96\x97\x95\x99) Tj",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()),
        );
        self::assertStringContainsString('/Encoding /WinAnsiEncoding', writeDocumentToString($document));
    }

    #[Test]
    public function it_rejects_characters_outside_the_win_ansi_matrix_with_helvetica_in_pdf_1_4(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'Helvetica' does not support the provided text.");

        $page->addText('Ł', new Position(10, 20), 'Helvetica', 12);
    }

    #[Test]
    public function it_wraps_open_text_box_content_into_multiple_text_lines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addParagraph('Hello world from PDF', new Position(10, 50), 40, 'Helvetica', 10, new FlowTextOptions(structureTag: StructureTag::Paragraph, lineHeight: 12.0, bottomMargin: 0.0));

        self::assertSame($page, $result);
        self::assertStringContainsString("/P << /MCID 0 >> BDC\nBT\n/F1 10 Tf\n10 50 Td\n(Hello) Tj\nET\nEMC", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("/P << /MCID 1 >> BDC\nBT\n/F1 10 Tf\n10 38 Td\n(world) Tj\nET\nEMC", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("/P << /MCID 2 >> BDC\nBT\n/F1 10 Tf\n10 26 Td\n(from) Tj\nET\nEMC", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("/P << /MCID 3 >> BDC\nBT\n/F1 10 Tf\n10 14 Td\n(PDF) Tj\nET\nEMC", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_wraps_an_open_text_box_without_creating_structure_when_no_tag_is_given(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', new Position(10, 50), 40, 'Helvetica', 10, new FlowTextOptions(lineHeight: 12.0, bottomMargin: 0.0));

        self::assertStringContainsString('(Hello) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('BDC', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('/Type /StructElem /S /P', writeDocumentToString($document));
    }

    #[Test]
    public function it_adds_text_inside_a_top_aligned_text_box(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addTextBox('Hello world from PDF', new Rect(10, 20, 40, 40), 'Helvetica', 10);

        self::assertSame($page, $result);
        self::assertStringContainsString("10 50 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("10 38 Td\n(world) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_supports_vertical_alignment_in_text_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addTextBox('Hello', new Rect(10, 20, 80, 30), 'Helvetica', 10, new TextBoxOptions(verticalAlign: VerticalAlign::MIDDLE));
        $page->addTextBox('World', new Rect(10, 60, 80, 30), 'Helvetica', 10, new TextBoxOptions(verticalAlign: VerticalAlign::BOTTOM));

        self::assertStringContainsString("10 30 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("10 60 Td\n(World) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_ignores_trailing_spaces_when_right_aligning_text_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addTextBox(
            'Hello  ',
            new Rect(10, 20, 40, 40),
            'Helvetica',
            10,
            new TextBoxOptions(align: HorizontalAlign::RIGHT),
        );

        self::assertStringContainsString("27.22 50 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_respects_padding_and_overflow_in_text_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addTextBox(
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

        self::assertStringContainsString("15 32 Td\n(Hello\x85) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_text_boxes_with_non_positive_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text box width must be greater than zero.');

        $page->addTextBox('Hello', new Rect(10, 20, 0, 40), 'Helvetica', 10);
    }

    #[Test]
    public function it_rejects_text_boxes_with_non_positive_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text box height must be greater than zero.');

        $page->addTextBox('Hello', new Rect(10, 20, 40, 0), 'Helvetica', 10);
    }

    #[Test]
    public function it_rejects_text_boxes_with_non_positive_line_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line height must be greater than zero.');

        $page->addTextBox('Hello', new Rect(10, 20, 40, 40), 'Helvetica', 10, new TextBoxOptions(lineHeight: 0.0));
    }

    #[Test]
    public function it_rejects_text_boxes_with_non_positive_max_lines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max lines must be greater than zero.');

        $page->addTextBox('Hello', new Rect(10, 20, 40, 40), 'Helvetica', 10, new TextBoxOptions(maxLines: 0));
    }

    #[Test]
    public function it_rejects_text_boxes_with_negative_padding(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text box padding must not be negative.');

        $page->addTextBox('Hello', new Rect(10, 20, 40, 40), 'Helvetica', 10, new TextBoxOptions(padding: new Insets(-1, 0, 0, 0)));
    }

    #[Test]
    public function it_rejects_text_boxes_without_positive_content_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text box content width must be greater than zero.');

        $page->addTextBox('Hello', new Rect(10, 20, 20, 40), 'Helvetica', 10, new TextBoxOptions(padding: new Insets(0, 10, 0, 10)));
    }

    #[Test]
    public function it_rejects_text_boxes_without_content_height_for_the_font_size(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text box content height must be at least the font size.');

        $page->addTextBox('Hello', new Rect(10, 20, 40, 10), 'Helvetica', 10, new TextBoxOptions(padding: new Insets(1, 0, 1, 0)));
    }

    #[Test]
    public function it_applies_opacity_to_each_line_of_a_wrapped_open_text_box(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', new Position(10, 50), 40, 'Helvetica', 10, new FlowTextOptions(lineHeight: 12.0, bottomMargin: 0.0, opacity: Opacity::fill(0.5)));

        self::assertStringContainsString('/ExtGState << /GS1 << /ca 0.5 >> >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getResources()));
        self::assertSame(4, substr_count(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()), '/GS1 gs'));
    }

    #[Test]
    public function it_applies_color_to_each_line_of_a_wrapped_open_text_box(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', new Position(10, 50), 40, 'Helvetica', 10, new FlowTextOptions(lineHeight: 12.0, bottomMargin: 0.0, color: Color::gray(0.5)));

        self::assertSame(4, substr_count(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()), '0.5 g'));
    }

    #[Test]
    public function it_clips_a_paragraph_to_the_configured_max_lines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            'Hello world from PDF',
            new Position(10, 50),
            40,
            'Helvetica',
            10,
            options: new FlowTextOptions(maxLines: 2),
        );

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("10 38 Td\n(world) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('(from) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('(PDF) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_appends_an_ellipsis_when_a_paragraph_is_truncated(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            'Hello world from PDF',
            new Position(10, 50),
            40,
            'Helvetica',
            10,
            options: new FlowTextOptions(maxLines: 2, overflow: TextOverflow::ELLIPSIS),
        );

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("10 38 Td\n(world\x85) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('(from) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('(PDF) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_appends_an_ellipsis_to_the_last_visible_segment_style(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Achtung:', Color::rgb(255, 0, 0)),
                new TextSegment(' Hello world from PDF', bold: true),
            ],
            new Position(10, 50),
            55,
            'Helvetica',
            10,
            options: new FlowTextOptions(maxLines: 2, overflow: TextOverflow::ELLIPSIS),
        );

        self::assertStringContainsString("1 0 0 rg\n(Achtung:) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("/F2 10 Tf\n10 38 Td\n(Hello worl\x85) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('(world) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_can_render_mixed_style_runs_within_a_single_paragraph(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Achtung:', Color::rgb(255, 0, 0)),
                new TextSegment('abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789.:,;()*!?\'@#<>$%&^+-=~'),
            ],
            new Position(10, 50),
            500,
            'Helvetica',
            10,
        );

        self::assertStringContainsString("1 0 0 rg\n(Achtung:) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('(abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("(0123456789.:,;\\(\\)*!?'@#<>$%&^+-=~) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_wraps_paragraph_runs_across_line_breaks(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Achtung:', Color::rgb(255, 0, 0)),
                new TextSegment(' Hello world from PDF'),
            ],
            new Position(10, 50),
            50,
            'Helvetica',
            10,
        );

        self::assertStringContainsString("10 50 Td\n1 0 0 rg\n(Achtung:) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("10 38 Td\n(Hello world) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("10 26 Td\n(from PDF) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_invalid_paragraph_run_arrays(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paragraph text arrays must contain only TextSegment instances.');

        /** @var mixed $invalidRuns */
        $invalidRuns = ['invalid'];

        $method = new ReflectionMethod($page, 'addParagraph');
        $method->invoke($page, $invalidRuns, new Position(10, 50), 50, 'Helvetica', 10);
    }

    #[Test]
    public function it_rejects_non_positive_flow_text_widths(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paragraph width must be greater than zero.');

        $page->addParagraph('Hello', new Position(10, 50), 0, 'Helvetica', 10);
    }

    #[Test]
    public function it_rejects_non_positive_flow_text_line_heights(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line height must be greater than zero.');

        $page->addParagraph('Hello', new Position(10, 50), 100, 'Helvetica', 10, new FlowTextOptions(lineHeight: 0.0));
    }

    #[Test]
    public function it_rejects_non_positive_flow_text_max_lines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max lines must be greater than zero.');

        $page->addParagraph('Hello', new Position(10, 50), 100, 'Helvetica', 10, new FlowTextOptions(maxLines: 0));
    }

    #[Test]
    public function it_rejects_non_positive_render_paragraph_line_widths(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paragraph width must be greater than zero.');

        $page->renderParagraphLines(
            [['segments' => [new TextSegment('Hello')], 'justify' => false]],
            10,
            50,
            0,
            'Helvetica',
            10,
        );
    }

    #[Test]
    public function it_rejects_non_positive_render_paragraph_line_heights(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line height must be greater than zero.');

        $page->renderParagraphLines(
            [['segments' => [new TextSegment('Hello')], 'justify' => false]],
            10,
            50,
            100,
            'Helvetica',
            10,
            lineHeight: 0.0,
        );
    }

    #[Test]
    public function it_keeps_justified_word_spacing_at_zero_when_a_line_contains_no_spaces(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->renderParagraphLines(
            [['segments' => [new TextSegment('Hello')], 'justify' => true]],
            10,
            50,
            100,
            'Helvetica',
            10,
            align: HorizontalAlign::JUSTIFY,
        );

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_uses_a_bold_standard_font_variant_for_bold_segments(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [new TextSegment('Achtung', bold: true)],
            new Position(10, 50),
            100,
            'Helvetica',
            10,
        );

        self::assertStringContainsString('/BaseFont /Helvetica-Bold', writeDocumentToString($document));
    }

    #[Test]
    public function it_uses_an_italic_standard_font_variant_for_italic_segments(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Times-Roman');
        $page = $document->addPage();

        $page->addParagraph(
            [new TextSegment('Hinweis', italic: true)],
            new Position(10, 50),
            100,
            'Times-Roman',
            10,
        );

        self::assertStringContainsString('/BaseFont /Times-Italic', writeDocumentToString($document));
    }

    #[Test]
    public function it_uses_a_configured_embedded_font_variant_for_bold_segments(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            fontConfig: [
                [
                    'baseFont' => 'CustomSans-Regular',
                    'path' => 'assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
                [
                    'baseFont' => 'CustomSans-Bold',
                    'path' => 'assets/fonts/NotoSans-Bold.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('CustomSans-Regular');
        $page = $document->addPage();

        $page->addParagraph(
            [new TextSegment('Achtung', bold: true)],
            new Position(10, 50),
            100,
            'CustomSans-Regular',
            10,
        );

        self::assertStringContainsString('/BaseFont /CustomSans-Bold', writeDocumentToString($document));
    }

    #[Test]
    public function it_falls_back_to_the_base_embedded_font_when_no_variant_candidate_is_available(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            fontConfig: [
                [
                    'baseFont' => 'CustomSans-Regular',
                    'path' => 'assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('CustomSans-Regular');
        $page = $document->addPage();

        $page->addParagraph(
            [new TextSegment('Fallback', bold: true)],
            new Position(10, 50),
            100,
            'CustomSans-Regular',
            10,
        );

        self::assertStringContainsString('/BaseFont /CustomSans-Regular', writeDocumentToString($document));
        self::assertStringNotContainsString('/BaseFont /CustomSans-Bold', writeDocumentToString($document));
    }

    #[Test]
    public function it_builds_variant_candidates_for_plain_bold_and_italic_font_requests(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $fonts = PageFonts::forPage($page);
        $method = new ReflectionMethod($fonts, 'buildVariantCandidates');

        self::assertSame(['CustomSans-Regular'], $method->invoke($fonts, 'CustomSans-Regular', false, false));
        self::assertSame(['CustomSans-BoldItalic', 'CustomSans-BoldOblique'], $method->invoke($fonts, 'CustomSans-Regular', true, true));
        self::assertSame(['CustomSans-Italic', 'CustomSans-Oblique'], $method->invoke($fonts, 'CustomSans-Regular', false, true));
    }

    #[Test]
    public function it_builds_bold_candidates_from_regular_base_fonts(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $fonts = PageFonts::forPage($page);
        $method = new ReflectionMethod($fonts, 'buildVariantCandidates');

        self::assertSame(['CustomSans-Bold'], $method->invoke($fonts, 'CustomSans-Regular', true, false));
    }

    #[Test]
    public function it_builds_italic_candidates_from_roman_base_fonts(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $fonts = PageFonts::forPage($page);
        $method = new ReflectionMethod($fonts, 'buildVariantCandidates');

        self::assertSame(['Times-Italic', 'Times-Oblique'], $method->invoke($fonts, 'Times-Roman', false, true));
    }

    #[Test]
    public function it_builds_generic_variant_candidates_for_base_fonts_without_regular_or_roman_suffixes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $fonts = PageFonts::forPage($page);
        $method = new ReflectionMethod($fonts, 'buildVariantCandidates');

        self::assertSame(['MyFont-Bold'], $method->invoke($fonts, 'MyFont', true, false));
    }

    #[Test]
    public function it_rejects_closed_paths_without_stroke_or_fill_in_the_shared_finisher(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $graphics = new PageGraphics($page);
        $path = new PathBuilder($page, $graphics);
        $path->moveTo(10, 10)->lineTo(20, 10)->lineTo(20, 20)->close();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Closed path requires either a stroke or a fill.');

        $graphics->finishClosedPath($path, null, null, null, null);
    }

    #[Test]
    public function it_fills_closed_paths_without_stroking_in_the_shared_finisher(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $graphics = new PageGraphics($page);
        $path = new PathBuilder($page, $graphics);
        $path->moveTo(10, 10)->lineTo(20, 10)->lineTo(20, 20)->close();

        $graphics->finishClosedPath($path, null, null, Color::gray(0.5), null);
        self::assertStringContainsString("0.5 g\n10 10 m", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("\nh\nf", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_rejects_transparent_text_for_pdf_a_1b(): void
    {
        $document = new Document(profile: Profile::pdfA1b());
        $document->registerFont(
            'NotoSans-Regular',
            'CIDFontType2',
            'Identity-H',
            true,
            __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
        );
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow transparency in the current implementation.');

        $page->addText(
            'Transparent',
            new Position(10, 50),
            'NotoSans-Regular',
            12,
            new TextOptions(opacity: Opacity::fill(0.5)),
        );
    }

    #[Test]
    public function it_rejects_transparent_rectangles_for_pdf_a_1a(): void
    {
        $document = new Document(profile: Profile::pdfA1a());
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1a does not allow transparency in the current implementation.');

        $page->addRectangle(
            new Rect(10, 10, 20, 20),
            fillColor: Color::gray(0.5),
            opacity: Opacity::both(0.4),
        );
    }

    #[Test]
    public function it_rejects_transparent_text_for_pdf_versions_below_1_4(): void
    {
        $document = new Document(profile: Profile::pdf13());
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.3 does not allow transparency. PDF 1.4 or higher is required.');

        $page->addText(
            'Transparent',
            new Position(10, 50),
            'Helvetica',
            12,
            new TextOptions(opacity: Opacity::fill(0.5)),
        );
    }

    #[Test]
    public function it_rejects_images_with_soft_masks_for_pdf_versions_below_1_4(): void
    {
        $document = new Document(profile: Profile::pdf13());
        $page = $document->addPage();

        $image = new Image(
            1,
            1,
            'DeviceRGB',
            'FlateDecode',
            "\x00\x00\x00",
            softMask: new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.3 does not allow transparency. PDF 1.4 or higher is required.');

        $page->addImage($image, new Position(10, 20), 10, 10);
    }

    #[Test]
    public function it_renders_underlines_and_strikethroughs_for_text_segments(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Underline', underline: true),
                new TextSegment(' Strike', strikethrough: true),
            ],
            new Position(10, 50),
            200,
            'Helvetica',
            10,
        );

        self::assertStringContainsString('re f', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertSame(2, substr_count(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()), 're f'));
    }

    #[Test]
    public function it_does_not_render_decorations_for_empty_text(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addText('', new Position(10, 50), 'Helvetica', 10, new TextOptions(underline: true, strikethrough: true));

        self::assertStringContainsString('() Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringNotContainsString('re f', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_does_not_underline_leading_spaces_before_a_decorated_segment(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph(
            [
                new TextSegment('Link:'),
                new TextSegment(' example', underline: true),
            ],
            new Position(10, 50),
            200,
            'Helvetica',
            10,
        );

        self::assertStringContainsString('(Link:) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('( example) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString('33.9 48.2 37.79 0.5 re f', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_does_not_underline_trailing_spaces_after_a_decorated_segment(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addText('example ', new Position(10, 50), 'Helvetica', 10, new TextOptions(underline: true));

        $expectedUnderlineWidth = $page->measureTextWidth('example', 'Helvetica', 10);
        $formattedWidth = rtrim(rtrim(sprintf('%.6F', $expectedUnderlineWidth), '0'), '.');

        self::assertStringContainsString('(example ) Tj', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("10 48.2 $formattedWidth 0.5 re f", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_centers_a_paragraph_within_the_available_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello', new Position(10, 50), 100, 'Helvetica', 10, new FlowTextOptions(align: HorizontalAlign::CENTER));

        self::assertStringContainsString("48.61 50 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_right_aligns_a_paragraph_within_the_available_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello', new Position(10, 50), 100, 'Helvetica', 10, new FlowTextOptions(align: HorizontalAlign::RIGHT));

        self::assertStringContainsString("87.22 50 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_justifies_automatically_wrapped_lines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph('Hello world from PDF', new Position(10, 50), 70, 'Helvetica', 10, new FlowTextOptions(align: HorizontalAlign::JUSTIFY));

        self::assertStringContainsString("10 50 Td\n(Hello) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("56.11 50 Td\n(world) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_does_not_justify_lines_created_by_hard_line_breaks(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->addParagraph("Hello world\nfrom PDF", new Position(10, 50), 100, 'Helvetica', 10, new FlowTextOptions(align: HorizontalAlign::JUSTIFY));

        self::assertStringContainsString("10 50 Td\n(Hello world) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
        self::assertStringContainsString("10 38 Td\n(from PDF) Tj", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($page->getContents()));
    }

    #[Test]
    public function it_creates_a_follow_up_page_when_an_open_text_box_reaches_the_bottom_margin(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $firstPage = $document->addPage(100.0, 60.0);

        $lastPage = $firstPage->addParagraph(
            'Hello world from PDF',
            new Position(10, 30),
            40,
            'Helvetica',
            10,
            new FlowTextOptions(structureTag: StructureTag::Paragraph, lineHeight: 12.0, bottomMargin: 15.0),
        );

        self::assertCount(2, $document->pages->pages);
        self::assertSame($document->pages->pages[1], $lastPage);
        self::assertStringContainsString('(Hello)', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($firstPage->getContents()));
        self::assertStringContainsString('(world)', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($firstPage->getContents()));
        self::assertStringContainsString('(from)', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($lastPage->getContents()));
        self::assertStringContainsString('(PDF)', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($lastPage->getContents()));
    }
}
