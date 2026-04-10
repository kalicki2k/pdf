<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\PublicApi;

use DateTimeImmutable;
use InvalidArgumentException;
use Kalle\Pdf\Action\ButtonAction;
use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFileStream;
use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Table\Definition\TableCaption;
use Kalle\Pdf\Layout\Table\Style\FooterStyle;
use Kalle\Pdf\Layout\Table\Style\HeaderStyle;
use Kalle\Pdf\Layout\Table\Style\RowStyle;
use Kalle\Pdf\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Layout\Text\Input\FlowTextOptions;
use Kalle\Pdf\Layout\Text\Input\ListOptions;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextBoxOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Layout\Value\TextOverflow;
use Kalle\Pdf\Page;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Page\Content\ImageOptions;
use Kalle\Pdf\Page\Content\PathBuilder;
use Kalle\Pdf\Page\Content\Style\BadgeStyle;
use Kalle\Pdf\Page\Content\Style\CalloutStyle;
use Kalle\Pdf\Page\Content\Style\PanelStyle;
use Kalle\Pdf\Page\Form\FormFieldFlags;
use Kalle\Pdf\Page\Form\FormFieldLabel;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Security\EncryptionOptions;
use Kalle\Pdf\Security\EncryptionPermissions;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\Table\Table;
use Kalle\Pdf\TaggedPdf\StructureTag;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;
use Kalle\Pdf\Text\TextFrame;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PublicApiTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    protected function tearDown(): void
    {
        $tempFiles = glob(sys_get_temp_dir() . '/pdf-public-api-*');

        if ($tempFiles === false) {
            return;
        }

        foreach ($tempFiles as $tempFile) {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_returns_the_small_public_page_type_from_the_public_document_api(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(PageSize::A4());

        self::assertInstanceOf(Page::class, $page);
    }

    #[Test]
    #[DataProvider('standardProfileProvider')]
    public function it_exposes_named_standard_pdf_versions_through_the_public_api(
        string $factory,
        float $expectedVersion,
        string $expectedHeader,
    ): void {
        $document = new Document(profile: Profile::{$factory}());

        self::assertSame('standard', $document->getProfile()->name());
        self::assertSame($expectedVersion, $document->getProfile()->version());
        self::assertStringStartsWith($expectedHeader, $this->writeDocument($document));
    }

    #[Test]
    public function it_exposes_the_selected_pdf_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        self::assertSame('PDF/A-2u', $document->getProfile()->name());
        self::assertSame(1.7, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_2b_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA2b());

        self::assertSame('PDF/A-2b', $document->getProfile()->name());
        self::assertSame(1.7, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_1b_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA1b());

        self::assertSame('PDF/A-1b', $document->getProfile()->name());
        self::assertSame(1.4, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_3b_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA3b());

        self::assertSame('PDF/A-3b', $document->getProfile()->name());
        self::assertSame(1.7, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_3u_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA3u());

        self::assertSame('PDF/A-3u', $document->getProfile()->name());
        self::assertSame(1.7, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_3a_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA3a());

        self::assertSame('PDF/A-3a', $document->getProfile()->name());
        self::assertSame(1.7, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_2a_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA2a());

        self::assertSame('PDF/A-2a', $document->getProfile()->name());
        self::assertSame(1.7, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_4_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA4());

        self::assertSame('PDF/A-4', $document->getProfile()->name());
        self::assertSame(2.0, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_4f_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA4f());

        self::assertSame('PDF/A-4f', $document->getProfile()->name());
        self::assertSame(2.0, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_a_4e_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA4e());

        self::assertSame('PDF/A-4e', $document->getProfile()->name());
        self::assertSame(2.0, $document->getProfile()->version());
    }

    #[Test]
    public function it_exposes_a_pdf_ua_1_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfUa1());

        self::assertSame('PDF/UA-1', $document->getProfile()->name());
        self::assertSame(1.7, $document->getProfile()->version());
    }

    #[Test]
    public function it_renders_a_minimal_pdf_a_2b_document_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2b(),
            title: 'PDF/A-2b',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo PDF/A', new Position(10, 50), 'NotoSans-Regular', 12);

        $rendered = $this->writeDocument($document);

        self::assertStringStartsWith("%PDF-1.7\n%\xE2\xE3\xCF\xD3\n", $rendered);
        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>B</pdfaid:conformance>', $rendered);
        self::assertStringContainsString('/Subtype /CIDFontType2', $rendered);
    }

    #[Test]
    public function it_can_write_the_public_document_to_a_stream(): void
    {
        $document = new Document(profile: Profile::standard(1.4), title: 'Stream output');
        $stream = fopen('php://temp', 'w+b');

        self::assertNotFalse($stream);

        $document->writeToStream($stream);
        rewind($stream);

        $writtenOutput = stream_get_contents($stream);

        fclose($stream);

        self::assertNotFalse($writtenOutput);
        self::assertStringStartsWith("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n", $writtenOutput);
        self::assertStringContainsString('/Title (Stream output)', $writtenOutput);
    }

    #[Test]
    public function it_writes_the_same_bytes_to_a_stream_and_file_for_a_representative_public_document(): void
    {
        $document = new Document(
            profile: Profile::pdf15(),
            title: 'Streaming contract',
            author: 'kalle/pdf',
            subject: 'Representative stream regression document',
            language: 'en-US',
            creator: 'PHPUnit',
            creatorTool: 'PublicApiTest',
        );
        $document->registerFont('Helvetica');
        $document->registerFont('Helvetica-Bold');
        $document->addKeyword('stream');
        $document->addKeyword('regression');
        $document->addAttachment('payload.txt', 'stream-contract');
        $document->addPageNumbers(new Position(280, 20), 'Helvetica', 9, '{{page}} / {{pages}}');
        $document->addHeader(static function (Page $page, int $pageNumber): void {
            $page->addText("Header $pageNumber", new Position(20, 820), 'Helvetica', 9);
        });
        $document->addFooter(static function (Page $page, int $pageNumber): void {
            $page->addText("Footer $pageNumber", new Position(20, 15), 'Helvetica', 9);
        });

        $guidesLayer = $document->addLayer('Guides');

        $cover = $document->addPage(PageSize::A4());
        $document->addOutline('Cover', $cover);
        $document->addDestination('cover', $cover);
        $cover->addText('Streaming contract', new Position(20, 800), 'Helvetica-Bold', 18);
        $cover->addText('This document exercises multiple public API paths.', new Position(20, 780), 'Helvetica', 11);
        $cover->addLink(new Rect(20, 740, 120, 14), 'https://example.com', 'Example');
        $cover->addImage(new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"), new Position(160, 760), 24, 24);
        $cover->layer($guidesLayer, static function (Page $page): void {
            $page->addRectangle(new Rect(18, 730, 170, 90));
        });
        $cover->createTextFrame(new Position(20, 710), 220)
            ->addParagraph('The writer output must stay byte-identical across the public stream and file APIs.', 'Helvetica', 11);
        $cover->createTable(new Position(20, 660), 220, [40, 100, 80])
            ->font('Helvetica', 10)
            ->addHeaderRow(['Key', 'Meaning', 'Value'])
            ->addRow(['Mode', 'Serialization path', 'stream + file'])
            ->addRow(['Check', 'Comparison', 'byte-identical']);

        $details = $document->addPage(PageSize::A4());
        $document->addOutline('Details', $details);
        $details->addInternalLink(new Rect(20, 740, 100, 14), 'cover', 'Back to cover');
        $details->addText('Second page', new Position(20, 800), 'Helvetica-Bold', 16);
        $details->addText('Attachment and optional content are included as well.', new Position(20, 780), 'Helvetica', 11);

        $stream = fopen('php://temp', 'w+b');
        $targetPath = sys_get_temp_dir() . '/pdf-public-api-' . uniqid('', true) . '.pdf';

        self::assertNotFalse($stream);

        $document->writeToStream($stream);
        rewind($stream);

        $streamOutput = stream_get_contents($stream);

        fclose($stream);

        $document->writeToFile($targetPath);
        $fileOutput = file_get_contents($targetPath);

        self::assertNotFalse($streamOutput);
        self::assertNotFalse($fileOutput);
        self::assertSame($streamOutput, $fileOutput);
    }

    #[Test]
    public function it_can_write_the_public_document_directly_to_a_file(): void
    {
        $targetPath = sys_get_temp_dir() . '/pdf-public-api-' . uniqid('', true) . '.pdf';
        $document = new Document(profile: Profile::standard(1.4), title: 'File output');
        $document->writeToFile($targetPath);

        $writtenOutput = file_get_contents($targetPath);

        self::assertNotFalse($writtenOutput);
        self::assertStringStartsWith("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n", $writtenOutput);
        self::assertStringContainsString('/Title (File output)', $writtenOutput);
    }

    #[Test]
    public function it_keeps_the_existing_target_file_when_file_output_fails(): void
    {
        $targetPath = sys_get_temp_dir() . '/pdf-public-api-' . uniqid('', true) . '.pdf';
        file_put_contents($targetPath, 'existing-content');

        $document = new Document(profile: Profile::pdfUa1());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires a document title.');

        try {
            $document->writeToFile($targetPath);
        } finally {
            $writtenOutput = file_get_contents($targetPath);

            self::assertNotFalse($writtenOutput);
            self::assertSame('existing-content', $writtenOutput);
        }
    }

    #[Test]
    public function it_renders_a_minimal_pdf_a_1b_document_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'PDF/A-1b',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo PDF/A', new Position(10, 50), 'NotoSans-Regular', 12);

        $rendered = $this->writeDocument($document);

        self::assertStringStartsWith("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n", $rendered);
        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('<pdfaid:part>1</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>B</pdfaid:conformance>', $rendered);
        self::assertStringContainsString('/Subtype /CIDFontType2', $rendered);
    }

    #[Test]
    public function it_renders_a_minimal_pdf_a_2a_document_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2a(),
            title: 'PDF/A-2a',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
                [
                    'baseFont' => 'NotoSans-Bold',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Bold.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');
        $document->registerFont('NotoSans-Bold');
        $document->addHeader(static function (Page $page, int $pageNumber): void {
            $page->addText("Header $pageNumber", new Position(10, 170), 'NotoSans-Regular', 8);
        });
        $document->addFooter(static function (Page $page, int $pageNumber): void {
            $page->addText("Footer $pageNumber", new Position(10, 10), 'NotoSans-Regular', 8);
        });

        $page = $document->addPage(PageSize::custom(150, 180));
        $frame = $page->createTextFrame(new Position(10, 160), 120, 120);
        $frame
            ->addHeading('Heading', 'NotoSans-Bold', 12, new ParagraphOptions(structureTag: StructureTag::Heading1))
            ->addParagraph('Paragraph', 'NotoSans-Regular', 10, new ParagraphOptions(structureTag: StructureTag::Paragraph))
            ->addBulletList(['One item'], 'NotoSans-Regular', 10, options: new ListOptions(structureTag: StructureTag::List));

        $table = $page->createTable(new Position(10, 110), 100, [50, 50]);
        $table
            ->font('NotoSans-Regular', 10)
            ->addHeaderRow(['Spalte A', 'Spalte B'])
            ->addRow(['Wert A', 'Wert B']);

        $page->addImage(
            new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
            new Position(115, 20),
            10,
            10,
            new ImageOptions(structureTag: StructureTag::Figure, altText: 'Dekorative Testgrafik'),
        );

        $rendered = $this->writeDocument($document);

        self::assertStringStartsWith("%PDF-1.7\n%\xE2\xE3\xCF\xD3\n", $rendered);
        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('/MarkInfo << /Marked true >>', $rendered);
        self::assertStringContainsString('/StructTreeRoot', $rendered);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>A</pdfaid:conformance>', $rendered);
        self::assertStringContainsString('/Artifact BMC', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /H1', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /L', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Lbl', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /LBody', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Table', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /TR', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /TH', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /TD', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Figure', $rendered);
        self::assertStringContainsString('/Alt (Dekorative Testgrafik)', $rendered);
    }

    #[Test]
    public function it_renders_a_minimal_pdf_ua_1_document_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
                [
                    'baseFont' => 'NotoSans-Bold',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Bold.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');
        $document->registerFont('NotoSans-Bold');

        $page = $document->addPage(PageSize::custom(150, 180));
        $frame = $page->createTextFrame(new Position(10, 160), 120, 120);
        $frame
            ->addHeading('Heading', 'NotoSans-Bold', 12, new ParagraphOptions(structureTag: StructureTag::Heading1))
            ->addParagraph('Paragraph', 'NotoSans-Regular', 10, new ParagraphOptions(structureTag: StructureTag::Paragraph))
            ->addBulletList(['One item'], 'NotoSans-Regular', 10, options: new ListOptions(structureTag: StructureTag::List));

        $page->addImage(
            new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
            new Position(115, 20),
            10,
            10,
            new ImageOptions(structureTag: StructureTag::Figure, altText: 'Dekorative Testgrafik'),
        );

        $rendered = $this->writeDocument($document);

        self::assertStringStartsWith("%PDF-1.7\n%\xE2\xE3\xCF\xD3\n", $rendered);
        self::assertStringContainsString('/ViewerPreferences << /DisplayDocTitle true >>', $rendered);
        self::assertStringContainsString('/MarkInfo << /Marked true >>', $rendered);
        self::assertStringContainsString('/Lang (de-DE)', $rendered);
        self::assertStringContainsString('/StructTreeRoot', $rendered);
        self::assertStringContainsString('xmlns:pdfuaid="http://www.aiim.org/pdfua/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $rendered);
        self::assertStringContainsString('/Title (PDF/UA-1)', $rendered);
        self::assertStringContainsString('/Alt (Dekorative Testgrafik)', $rendered);
    }

    #[Test]
    public function it_rejects_untagged_images_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $page = $document->addPage(PageSize::custom(100, 100));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires images to be tagged as Figure or rendered as artifacts in the current implementation.');

        $page->addImage(new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"), new Position(10, 20), 10, 10);
    }

    #[Test]
    public function it_rejects_figure_images_without_alt_text_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $page = $document->addPage(PageSize::custom(100, 100));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires alt text for Figure images in the current implementation.');

        $page->addImage(
            new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
            new Position(10, 20),
            10,
            10,
            new ImageOptions(structureTag: StructureTag::Figure),
        );
    }

    #[Test]
    public function it_renders_an_accessible_text_field_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'PDF/UA-1');
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addTextField('field', new Rect(10, 20, 40, 15), 'value', self::pdfUaRegularFont(), 10, accessibleName: 'Customer name');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Widget', $rendered);
        self::assertStringContainsString('/TU (Customer name)', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Customer name\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_visible_text_field_labels_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'PDF/UA-1');
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addTextField(
            'field',
            new Rect(10, 20, 40, 15),
            'value',
            self::pdfUaRegularFont(),
            10,
            fieldLabel: new FormFieldLabel(
                'Customer name',
                new Position(10, 42),
                self::pdfUaRegularFont(),
                10,
            ),
        );

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/TU (Customer name)', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
        self::assertSame(1, substr_count($page->getContents()->render(), '/P << /MCID'));
        self::assertStringContainsString('/Type /StructElem /S /Div', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Customer name\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_an_accessible_signature_field_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addSignatureField('signature', new Rect(10, 20, 40, 15), 'Approval signature');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/FT /Sig', $rendered);
        self::assertStringContainsString('/TU (Approval signature)', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Approval signature\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_an_accessible_checkbox_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addCheckbox('check', new Position(10, 20), 12, true, 'Accept terms');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/T (check)', $rendered);
        self::assertStringContainsString('/V /Yes', $rendered);
        self::assertStringContainsString('/TU (Accept terms)', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Accept terms\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_an_accessible_push_button_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'PDF/UA-1');
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addPushButton('save_form', 'Speichern', new Rect(10, 20, 80, 16), self::pdfUaRegularFont(), 12, accessibleName: 'Save form');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/T (save_form)', $rendered);
        self::assertStringContainsString('/CA (Speichern)', $rendered);
        self::assertStringContainsString('/TU (Save form)', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Save form\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_accessible_radio_buttons_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addRadioButton('delivery', 'standard', new Position(10, 20), 12, true, 'Standard delivery');
        $page->addRadioButton('delivery', 'express', new Position(30, 20), 12, false, 'Express delivery');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/T (delivery)', $rendered);
        self::assertStringContainsString('/TU (delivery)', $rendered);
        self::assertStringContainsString('/V /standard', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/StructParent 2', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Standard delivery\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Express delivery\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_an_accessible_combo_box_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'PDF/UA-1');
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addComboBox(
            'country',
            new Rect(10, 20, 80, 12),
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            self::pdfUaRegularFont(),
            12,
            accessibleName: 'Country selection',
        );

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/T (country)', $rendered);
        self::assertStringContainsString('/V (de)', $rendered);
        self::assertStringContainsString('/TU (Country selection)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Country selection\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_an_accessible_list_box_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'PDF/UA-1');
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addListBox(
            'topics',
            new Rect(10, 20, 80, 40),
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            'forms',
            self::pdfUaRegularFont(),
            12,
            accessibleName: 'Topics selection',
        );

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/T (topics)', $rendered);
        self::assertStringContainsString('/V (forms)', $rendered);
        self::assertStringContainsString('/TU (Topics selection)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Form \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Topics selection\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_an_accessible_rect_link_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addLink(new Rect(10, 20, 30, 10), 'https://example.com', 'Read more');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Link', $rendered);
        self::assertStringContainsString('/Contents (Read more)', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Link \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Read more\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_text_annotations_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addTextAnnotation(new Rect(10, 20, 10, 10), 'Kommentar', 'QA');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Text', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Annot \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Kommentar\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_renders_file_attachment_annotations_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');
        $page = $document->addPage(PageSize::custom(100, 100));
        $file = $document->getAttachment('demo.txt');

        self::assertNotNull($file);

        $page->addFileAttachment(new Rect(10, 20, 12, 14), $file, 'Graph', 'Demo attachment');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /FileAttachment', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Annot \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Demo attachment\) \/K \[<< \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_requires_accessible_names_for_rect_links_in_pdf_ua_1_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'PDF/UA-1',
            language: 'de-DE',
        );
        $page = $document->addPage(PageSize::custom(100, 100));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires an accessible name for standalone link annotations.');

        $page->addLink(new Rect(10, 20, 30, 10), 'https://example.com');
    }

    #[Test]
    public function it_renders_tagged_text_links_for_pdf_ua_1_through_the_public_api(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'PDF/UA-1');
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addText(
            'Weiterlesen',
            new Position(10, 20),
            self::pdfUaRegularFont(),
            12,
            new TextOptions(link: LinkTarget::externalUrl('https://example.com')),
        );

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Link', $rendered);
        self::assertStringContainsString('/StructParent 1', $rendered);
        self::assertStringContainsString('/Contents (Weiterlesen)', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Link', $rendered);
        self::assertStringContainsString('/Alt (Weiterlesen)', $rendered);
        self::assertStringContainsString('xmlns:pdfuaid="http://www.aiim.org/pdfua/ns/id/"', $rendered);
    }

    #[Test]
    public function it_nests_tagged_public_text_links_inside_existing_structure_tags_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'PDF/UA-1');
        $page = $document->addPage(PageSize::custom(100, 100));

        $page->addText(
            'Weiterlesen',
            new Position(10, 20),
            self::pdfUaRegularFont(),
            12,
            new TextOptions(
                structureTag: StructureTag::Paragraph,
                link: LinkTarget::externalUrl('https://example.com'),
            ),
        );

        $rendered = $this->writeDocument($document);

        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/P \/P \d+ 0 R \/K \[\d+ 0 R\]/', $rendered);
        self::assertStringContainsString('/Contents (Weiterlesen)', $rendered);
        self::assertStringContainsString('/Alt (Weiterlesen)', $rendered);
        self::assertMatchesRegularExpression('/\/Type \/StructElem \/S \/Link \/P \d+ 0 R \/Pg \d+ 0 R \/Alt \(Weiterlesen\) \/K \[0 << \/Type \/OBJR \/Obj \d+ 0 R \/Pg \d+ 0 R >>\]/', $rendered);
    }

    #[Test]
    public function it_binds_public_panel_links_to_visible_text_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'PDF/UA-1', registerBold: true);
        $page = $document->addPage(PageSize::custom(160, 120));

        $page->addPanel(
            'Body',
            10,
            20,
            100,
            50,
            'Title',
            self::pdfUaRegularFont(),
            new PanelStyle(),
            null,
            LinkTarget::externalUrl('https://example.com'),
        );

        $rendered = $this->writeDocument($document);

        self::assertSame(2, substr_count($rendered, '/Subtype /Link'));
        self::assertGreaterThanOrEqual(2, substr_count($rendered, '/Type /StructElem /S /Link'));
        self::assertStringContainsString('/Contents (Title)', $rendered);
        self::assertStringContainsString('/Contents (Body)', $rendered);
        self::assertStringContainsString('/Alt (Title)', $rendered);
        self::assertStringContainsString('/Alt (Body)', $rendered);
        self::assertGreaterThanOrEqual(2, substr_count($rendered, 'BT'));
    }

    #[Test]
    public function it_renders_a_minimal_pdf_a_2u_document_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo PDF/A', new Position(10, 50), 'NotoSans-Regular', 12);

        $rendered = $this->writeDocument($document);

        self::assertStringStartsWith("%PDF-1.7\n%\xE2\xE3\xCF\xD3\n", $rendered);
        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $rendered);
        self::assertStringContainsString('/Subtype /CIDFontType2', $rendered);
    }

    #[Test]
    public function it_marks_pdf_a_2u_link_annotations_as_printable_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u Links',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo Link', new Position(10, 50), 'NotoSans-Regular', 12);
        $page->addLink(new Rect(10, 45, 40, 10), 'https://example.com');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Link', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
    }

    #[Test]
    public function it_rejects_pdf_a_2u_file_attachment_annotations_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage(PageSize::custom(100, 100));
        $file = new FileSpecification(8, 'demo.txt', new EmbeddedFileStream(7, 'hello'), 'Demo attachment');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow embedded file attachments.');

        $page->addFileAttachment(new Rect(10, 20, 12, 14), $file, 'Graph', 'Anhang');
    }

    #[Test]
    public function it_allows_pdf_a_2u_popups_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u Popup',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo Popup', new Position(10, 70), 'NotoSans-Regular', 12);
        $page->addTextAnnotation(new Rect(10, 20, 10, 10), 'Kommentar', 'QA');

        $popupParent = $this->internalPage($page)->getAnnotations()[0];
        $page->addPopupAnnotation($popupParent, new Rect(25, 20, 30, 20), true);

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Text', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
        self::assertStringContainsString('/Subtype /Popup', $rendered);
        self::assertStringContainsString('/Popup ', $rendered);
    }

    #[Test]
    public function it_renders_pdf_a_2u_text_annotations_with_flags_and_appearance_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u Notes',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo Notiz', new Position(10, 50), 'NotoSans-Regular', 12);
        $page->addTextAnnotation(new Rect(10, 20, 10, 10), 'Kommentar', 'QA');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Text', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
    }

    #[Test]
    public function it_renders_pdf_a_2u_free_text_annotations_with_flags_and_appearance_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u FreeText',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo Freitext', new Position(10, 70), 'NotoSans-Regular', 12);
        $page->addFreeTextAnnotation(new Rect(10, 20, 40, 20), 'Kommentar', 'NotoSans-Regular', 10);

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /FreeText', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
    }

    #[Test]
    public function it_renders_pdf_a_2u_highlight_annotations_with_flags_and_appearance_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u Highlight',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo Highlight', new Position(10, 70), 'NotoSans-Regular', 12);
        $page->addHighlightAnnotation(new Rect(10, 65, 20, 8), Color::rgb(1, 1, 0), 'Markiert', 'QA');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Highlight', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
    }

    #[Test]
    public function it_renders_pdf_a_2u_underline_annotations_with_flags_and_appearance_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u Underline',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo Underline', new Position(10, 70), 'NotoSans-Regular', 12);
        $page->addUnderlineAnnotation(new Rect(10, 65, 20, 8), Color::rgb(0, 0, 1), 'Unterstrichen', 'QA');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Underline', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
    }

    #[Test]
    public function it_renders_pdf_a_2u_strike_out_annotations_with_flags_and_appearance_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u StrikeOut',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo StrikeOut', new Position(10, 70), 'NotoSans-Regular', 12);
        $page->addStrikeOutAnnotation(new Rect(10, 65, 20, 8), Color::rgb(1, 0, 0), 'Durchgestrichen', 'QA');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /StrikeOut', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
    }

    #[Test]
    public function it_renders_pdf_a_2u_squiggly_annotations_with_flags_and_appearance_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u Squiggly',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(100, 100));
        $page->addText('Hallo Squiggly', new Position(10, 70), 'NotoSans-Regular', 12);
        $page->addSquigglyAnnotation(new Rect(10, 65, 20, 8), Color::rgb(1, 0, 1), 'Wellig', 'QA');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Squiggly', $rendered);
        self::assertStringContainsString('/F 4', $rendered);
        self::assertStringContainsString('/AP << /N ', $rendered);
    }

    #[Test]
    public function it_renders_remaining_pdf_a_2u_non_widget_annotations_with_flags_and_appearances_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'PDF/A-2u Remaining Annotations',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');

        $page = $document->addPage(PageSize::custom(200, 120));
        $page->addText('Hallo Rest', new Position(10, 100), 'NotoSans-Regular', 12);
        $page->addStampAnnotation(new Rect(10, 80, 20, 10), 'Approved', Color::rgb(0, 128, 0), 'Freigegeben', 'QA');
        $page->addSquareAnnotation(new Rect(35, 75, 20, 20), Color::rgb(1, 0, 0), Color::gray(0.9), 'Kasten', 'QA');
        $page->addCircleAnnotation(new Rect(60, 75, 20, 20), Color::rgb(0, 0, 1), Color::gray(0.9), 'Kreis', 'QA');
        $page->addInkAnnotation(new Rect(85, 75, 20, 20), [[[85.0, 75.0], [95.0, 85.0]]], Color::rgb(0, 0, 0), 'Ink', 'QA');
        $page->addLineAnnotation(new Position(110, 75), new Position(140, 95), Color::rgb(0, 0, 0), 'Linie', 'QA');
        $page->addPolyLineAnnotation([[145, 75], [155, 95], [165, 80]], Color::rgb(0, 0, 1), 'PolyLine', 'QA');
        $page->addPolygonAnnotation([[170, 75], [180, 95], [190, 80]], Color::rgb(1, 0, 0), Color::gray(0.9), 'Polygon', 'QA');
        $page->addCaretAnnotation(new Rect(10, 55, 10, 10), 'Einfuegen', 'QA', 'P');

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('/Subtype /Stamp', $rendered);
        self::assertStringContainsString('/Subtype /Square', $rendered);
        self::assertStringContainsString('/Subtype /Circle', $rendered);
        self::assertStringContainsString('/Subtype /Ink', $rendered);
        self::assertStringContainsString('/Subtype /Line', $rendered);
        self::assertStringContainsString('/Subtype /PolyLine', $rendered);
        self::assertStringContainsString('/Subtype /Polygon', $rendered);
        self::assertStringContainsString('/Subtype /Caret', $rendered);
        self::assertGreaterThanOrEqual(8, substr_count($rendered, '/F 4'));
        self::assertGreaterThanOrEqual(8, substr_count($rendered, '/AP << /N '));
    }

    #[Test]
    public function it_renders_a_minimal_pdf_a_3a_document_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::pdfA3a(),
            title: 'PDF/A-3a',
            language: 'de-DE',
            fontConfig: [
                [
                    'baseFont' => 'NotoSans-Regular',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
                [
                    'baseFont' => 'NotoSans-Bold',
                    'path' => __DIR__ . '/../../assets/fonts/NotoSans-Bold.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );
        $document->registerFont('NotoSans-Regular');
        $document->registerFont('NotoSans-Bold');
        $document->addAttachment('source-data.xml', '<root/>', 'Machine-readable source', 'application/xml');

        $page = $document->addPage(PageSize::custom(150, 180));
        $frame = $page->createTextFrame(new Position(10, 160), 120, 120);
        $frame
            ->addHeading('Heading', 'NotoSans-Bold', 12, new ParagraphOptions(structureTag: StructureTag::Heading1))
            ->addParagraph('Paragraph', 'NotoSans-Regular', 10, new ParagraphOptions(structureTag: StructureTag::Paragraph))
            ->addBulletList(['One item'], 'NotoSans-Regular', 10, options: new ListOptions(structureTag: StructureTag::List));

        $table = $page->createTable(new Position(10, 110), 100, [50, 50]);
        $table
            ->font('NotoSans-Regular', 10)
            ->addHeaderRow(['Spalte A', 'Spalte B'])
            ->addRow(['Wert A', 'Wert B']);

        $page->addImage(
            new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
            new Position(115, 20),
            10,
            10,
            new ImageOptions(structureTag: StructureTag::Figure, altText: 'Dekorative Testgrafik'),
        );

        $rendered = $this->writeDocument($document);

        self::assertStringStartsWith("%PDF-1.7\n%\xE2\xE3\xCF\xD3\n", $rendered);
        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('/MarkInfo << /Marked true >>', $rendered);
        self::assertStringContainsString('/StructTreeRoot', $rendered);
        self::assertStringContainsString('<pdfaid:part>3</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>A</pdfaid:conformance>', $rendered);
        self::assertStringContainsString('/AF [', $rendered);
        self::assertStringContainsString('/AFRelationship /Data', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Figure', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Table', $rendered);
    }

    #[Test]
    public function it_passes_the_public_page_type_to_header_callbacks(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');

        $receivedPage = null;
        $document->addHeader(static function (Page $page, int $pageNumber) use (&$receivedPage): void {
            $receivedPage = $page;
            $page->addText("Header $pageNumber", new Position(10, 90), 'Helvetica', 10);
        });

        $document->addPage(PageSize::custom(100, 100));
        $this->writeDocument($document);

        self::assertInstanceOf(Page::class, $receivedPage);
        self::assertStringContainsString('(Header 1) Tj', $this->writeDocument($document));
    }

    #[Test]
    public function it_can_exclude_a_public_page_from_logical_numbering(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');

        $coverPage = $document->addPage(PageSize::custom(100, 100));
        $contentPage = $document->addPage(PageSize::custom(100, 100));

        $document->excludePageFromNumbering($coverPage);
        $document->addOutline('Kapitel', $contentPage);
        $tocPage = $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 10,
                placement: TableOfContentsPlacement::start(),
                useLogicalPageNumbers: true,
            ),
        );

        self::assertStringContainsString('(2) Tj', $this->writeDocument($document));
        self::assertInstanceOf(Page::class, $tocPage);
    }

    #[Test]
    public function it_keeps_text_frame_and_table_page_access_on_the_public_page_type(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(PageSize::custom(200, 200));

        $textFrame = $page->createTextFrame(new Position(10, 190), 100);
        $table = $page->createTable(new Position(10, 190), 100, [50, 50]);

        self::assertInstanceOf(TextFrame::class, $textFrame);
        self::assertInstanceOf(Table::class, $table);
        self::assertInstanceOf(Page::class, $textFrame->getPage());
        self::assertInstanceOf(Page::class, $table->getPage());
    }

    #[Test]
    public function it_exposes_table_captions_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(PageSize::custom(200, 200));

        $page->createTable(new Position(10, 190), 100, [50, 50])
            ->caption(new TableCaption('Uebersicht', size: 11))
            ->addHeaderRow(['Name', 'Wert'])
            ->addRow(['A', '1']);

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('(Uebersicht) Tj', $rendered);
        self::assertStringContainsString('(Name) Tj', $rendered);
    }

    #[Test]
    public function it_exposes_and_updates_document_metadata_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            creator: 'Initial Creator',
            creatorTool: 'Initial Tool',
        );

        self::assertSame('Initial Creator', $document->getCreator());
        self::assertSame('Initial Tool', $document->getCreatorTool());

        $document
            ->setCreator('Updated Creator')
            ->setProducer('Updated Producer')
            ->setCreatorTool('Updated Tool');

        self::assertSame('Updated Creator', $document->getCreator());
        self::assertSame('Updated Producer', $document->getProducer());
        self::assertSame('Updated Tool', $document->getCreatorTool());
    }

    #[Test]
    public function it_forwards_public_api_registrations_to_the_internal_document(): void
    {
        $document = new Document(profile: Profile::standard(1.5));
        $page = $document->addPage(PageSize::custom(100, 100));

        $attachmentPath = sys_get_temp_dir() . '/pdf-public-api-attachment.txt';
        file_put_contents($attachmentPath, 'attachment-body');

        $layer = $document->addLayer('Layer A', false);
        $document
            ->addOutline('Kapitel', $page)
            ->addDestination('chapter-1', $page)
            ->addAttachment('manual.txt', 'hello world', 'manual')
            ->addAttachmentFromFile($attachmentPath, 'copy.txt')
            ->addKeyword('pdf')
            ->registerFont('Helvetica')
            ->encrypt(new EncryptionOptions('user', 'owner', EncryptionPermissions::readOnly(), EncryptionAlgorithm::RC4_128));

        $internalDocument = $this->internalDocument($document);

        self::assertSame($layer, $internalDocument->getOptionalContentGroups()[0]);
        self::assertArrayHasKey('chapter-1', $internalDocument->getDestinations());
        self::assertCount(1, $internalDocument->outlineRoot?->getItems() ?? []);
        self::assertCount(2, $internalDocument->getAttachments());
        self::assertNotNull($internalDocument->getAttachment('manual.txt'));
        self::assertNotNull($internalDocument->getAttachment('copy.txt'));
        self::assertSame('pdf', $internalDocument->getKeywords()[0]);
        self::assertNotNull($internalDocument->getFontByBaseFont('Helvetica'));
        self::assertNotNull($internalDocument->getEncryptionOptions());
    }

    #[Test]
    public function it_allows_associated_files_for_pdf_2_0_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdf20());

        $document->addAttachment(
            'data.json',
            '{"items":[]}',
            'Machine-readable source',
            'application/json',
            AssociatedFileRelationship::DATA,
        );

        $attachment = $this->internalDocument($document)->getAttachment('data.json');
        $rendered = $this->writeDocument($document);

        self::assertInstanceOf(FileSpecification::class, $attachment);
        self::assertStringContainsString('/AFRelationship /Data', $attachment->render());
        self::assertStringContainsString('/AF [', $rendered);
    }

    #[Test]
    public function it_rejects_associated_files_for_pdf_1_7_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdf17());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.7 does not allow associated files. PDF 2.0 or a supporting archival profile is required.');

        $document->addAttachment(
            'data.json',
            '{"items":[]}',
            'Machine-readable source',
            'application/json',
            AssociatedFileRelationship::DATA,
        );
    }

    #[Test]
    public function it_rejects_public_api_rc4_40_encryption_for_pdf_version_1_2(): void
    {
        $document = new Document(profile: Profile::pdf12());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.2 does not allow RC4 40-bit encryption. PDF 1.3 or higher is required.');

        $document->encrypt(new EncryptionOptions(
            'user',
            'owner',
            EncryptionPermissions::all(),
            EncryptionAlgorithm::RC4_40,
        ));
    }

    #[Test]
    public function it_rejects_public_api_aes_128_encryption_for_pdf_version_1_5(): void
    {
        $document = new Document(profile: Profile::pdf15());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.5 does not allow AES-128 encryption. PDF 1.6 or higher is required.');

        $document->encrypt(new EncryptionOptions(
            'user',
            'owner',
            EncryptionPermissions::all(),
            EncryptionAlgorithm::AES_128,
        ));
    }

    #[Test]
    public function it_rejects_public_api_aes_256_encryption_for_pdf_version_1_6(): void
    {
        $document = new Document(profile: Profile::pdf16());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.6 does not allow AES-256 encryption. PDF 1.7 or higher is required.');

        $document->encrypt(new EncryptionOptions(
            'user',
            'owner',
            EncryptionPermissions::all(),
            EncryptionAlgorithm::AES_256,
        ));
    }

    #[Test]
    public function it_adds_footers_and_page_numbers_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $document->addFooter(static function (Page $page, int $pageNumber): void {
            $page->addText("Footer $pageNumber", new Position(10, 10), 'Helvetica', 10);
        });
        $document->addPageNumbers(new Position(10, 20), 'Helvetica', 10, '{{page}} / {{pages}}');

        $document->addPage(PageSize::custom(100, 100));
        $document->addPage(PageSize::custom(100, 100));

        $rendered = $this->writeDocument($document);

        self::assertStringContainsString('(Footer 1) Tj', $rendered);
        self::assertStringContainsString('(Footer 2) Tj', $rendered);
        self::assertStringContainsString('(1 / 2) Tj', $rendered);
        self::assertStringContainsString('(2 / 2) Tj', $rendered);
    }

    #[Test]
    public function it_exposes_document_dates_default_pages_attachments_and_table_of_contents_through_the_public_api(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            title: 'Public API Document',
            author: 'Alice',
            subject: 'Coverage',
            language: 'de',
            creator: 'Creator',
            creatorTool: 'Tool',
        );
        $document->registerFont('Helvetica');

        $creationDate = $document->getCreationDate();
        $modificationDate = $document->getModificationDate();
        $firstPage = $document->addPage();
        $secondPage = $document->addPage(PageSize::custom(100, 100));

        $document
            ->addAttachment('guide.txt', 'Guide body', 'Guide')
            ->addOutline('Kapitel', $secondPage);

        $attachment = $document->getAttachment('guide.txt');
        $tocPage = $document->addTableOfContents();
        $rendered = $this->writeDocument($document);

        self::assertInstanceOf(DateTimeImmutable::class, $creationDate);
        self::assertInstanceOf(DateTimeImmutable::class, $modificationDate);
        self::assertGreaterThanOrEqual($creationDate->getTimestamp(), $modificationDate->getTimestamp());
        self::assertEqualsWithDelta(595.28, $firstPage->getWidth(), 0.01);
        self::assertEqualsWithDelta(841.89, $firstPage->getHeight(), 0.01);
        self::assertInstanceOf(FileSpecification::class, $attachment);
        self::assertSame($this->internalDocument($document)->getAttachment('guide.txt'), $attachment);
        self::assertInstanceOf(Page::class, $tocPage);
        self::assertStringStartsWith('%PDF-', $rendered);
    }

    #[Test]
    public function it_forwards_basic_public_page_operations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(120, 140));

        $returnedPage = $page
            ->addText('Hello', new Position(10, 100), 'Helvetica', 10)
            ->addLine(new Position(10, 90), new Position(40, 90), 1.0, Color::rgb(0, 0, 0))
            ->addRectangle(new Rect(10, 60, 20, 10), 1.0, Color::rgb(0, 0, 0), Color::rgb(1, 0, 0))
            ->addLink(new Rect(10, 40, 30, 10), 'https://example.com')
            ->addInternalLink(new Rect(10, 25, 30, 10), 'chapter-1');

        $internalPage = $this->internalPage($page);

        self::assertSame($page, $returnedPage);
        self::assertSame(120.0, $page->getWidth());
        self::assertSame(140.0, $page->getHeight());
        self::assertStringContainsString('(Hello) Tj', $internalPage->getContents()->render());
        self::assertCount(2, $internalPage->getAnnotations());
    }

    #[Test]
    public function it_wraps_public_page_layers_with_public_page_instances(): void
    {
        $document = new Document(profile: Profile::standard(1.5));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(120, 140));

        $receivedPage = null;
        $page->layer('Layer A', static function (Page $layerPage) use (&$receivedPage): void {
            $receivedPage = $layerPage;
            $layerPage->addText('Layered', new Position(10, 100), 'Helvetica', 10);
        });

        $internalPage = $this->internalPage($page);

        self::assertInstanceOf(Page::class, $receivedPage);
        self::assertStringContainsString('(Layered) Tj', $internalPage->getContents()->render());
    }

    #[Test]
    public function it_rejects_public_page_layers_for_pdf_version_1_4(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(120, 140));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.4 does not allow optional content groups (layers). PDF 1.5 or higher is required.');

        $page->layer('Layer A', static function (Page $layerPage): void {
            $layerPage->addText('Layered', new Position(10, 100), 'Helvetica', 10);
        });
    }

    #[Test]
    public function it_rejects_public_page_transparency_for_pdf_version_1_3(): void
    {
        $document = new Document(profile: Profile::pdf13());
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(120, 140));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.3 does not allow transparency. PDF 1.4 or higher is required.');

        $page->addText('Transparent', new Position(10, 100), 'Helvetica', 10, new TextOptions(opacity: Opacity::fill(0.5)));
    }

    #[Test]
    public function it_rejects_raw_public_page_graphics_for_pdf_ua_1(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage(PageSize::custom(120, 140));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires lines, shapes and paths to be rendered as artifacts in the current implementation.');

        $page->addRectangle(new Rect(10, 60, 20, 10), 1.0, Color::rgb(0, 0, 0), Color::rgb(1, 0, 0));
    }

    #[Test]
    public function it_rejects_win_ansi_font_registration_for_pdf_version_1_0(): void
    {
        $document = new Document(profile: Profile::pdf10());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.0 does not allow WinAnsiEncoding for standard fonts. PDF 1.1 or higher is required.');

        $document->registerFont('Helvetica', encoding: 'WinAnsiEncoding');
    }

    #[Test]
    public function it_forwards_public_table_operations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(200, 200));

        $table = $page->createTable(new Position(10, 190), 100, [50, 50]);
        $returnedTable = $table
            ->font('Helvetica', 10)
            ->style(new TableStyle())
            ->rowStyle(new RowStyle())
            ->headerStyle(new HeaderStyle())
            ->footerStyle(new FooterStyle())
            ->addHeaderRow(['H1', 'H2'], repeat: false)
            ->addRow(['A', 'B'])
            ->addFooterRow(['F1', 'F2']);

        self::assertSame($table, $returnedTable);
        self::assertInstanceOf(Page::class, $table->getPage());
        self::assertLessThan(190.0, $table->getCursorY());
    }

    #[Test]
    public function it_forwards_public_text_frame_operations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(200, 200));

        $frame = $page->createTextFrame(new Position(10, 190), 100);
        $returnedFrame = $frame
            ->addText('Intro', 'Helvetica', 10)
            ->addParagraph('Paragraph', 'Helvetica', 10)
            ->addBulletList(['One', 'Two'], 'Helvetica', 10)
            ->addNumberedList(['First', 'Second'], 'Helvetica', 10)
            ->addHeading('Heading', 'Helvetica', 12)
            ->addSpacer(8.0);

        self::assertSame($frame, $returnedFrame);
        self::assertInstanceOf(Page::class, $frame->getPage());
        self::assertLessThan(190.0, $frame->getCursorY());

        $rendered = $this->internalPage($page)->getContents()->render();

        self::assertStringContainsString('(Intro) Tj', $rendered);
        self::assertStringContainsString('(Paragraph) Tj', $rendered);
        self::assertStringContainsString('(One) Tj', $rendered);
        self::assertStringContainsString('(First) Tj', $rendered);
        self::assertStringContainsString('(Heading) Tj', $rendered);
    }

    #[Test]
    public function it_forwards_public_page_text_shape_and_measurement_operations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(200, 220));

        $path = $page->addPath();
        $image = new Image(1, 1, 'DeviceRGB', 'FlateDecode', 'x');

        $returnedPage = $page
            ->addBadge('Badge', new Position(10, 200), 'Helvetica', 10, new BadgeStyle(fillColor: Color::rgb(230, 230, 230), textColor: Color::rgb(0, 0, 0)))
            ->addPanel('Body', 10, 135, 80, 55, 'Title', 'Helvetica', new PanelStyle())
            ->addCallout('Body', 100, 135, 80, 55, 90, 125, 'Callout', 'Helvetica', new CalloutStyle())
            ->addFlowText('Flow text', new Position(10, 130), 80, 'Helvetica', 10, new FlowTextOptions())
            ->addTextBox('Box text', new Rect(10, 100, 80, 20), 'Helvetica', 10, new TextBoxOptions())
            ->addRoundedRectangle(new Rect(10, 70, 20, 10), 2.0, 1.0, Color::rgb(0, 0, 0), Color::rgb(0, 1, 0))
            ->addCircle(50, 75, 5.0, 1.0, Color::rgb(0, 0, 0), Color::rgb(0, 0, 1))
            ->addEllipse(80, 75, 7.0, 4.0, 1.0, Color::rgb(0, 0, 0), Color::rgb(1, 0, 1))
            ->addPolygon([[100, 70], [110, 80], [120, 70]], 1.0, Color::rgb(0, 0, 0), Color::rgb(1, 1, 0))
            ->addArrow(new Position(10, 50), new Position(40, 50), color: Color::rgb(0, 0, 0))
            ->addStar(70, 45, 5, 8.0, 4.0, 1.0, Color::rgb(0, 0, 0), Color::rgb(255, 128, 0))
            ->addImage($image, new Position(130, 40), 10, 10);

        self::assertInstanceOf(PathBuilder::class, $path);
        self::assertSame($page, $returnedPage);
        self::assertGreaterThan(0, $page->countParagraphLines('Hello world', 'Helvetica', 10, 40));
        self::assertSame(22.78, $page->measureTextWidth('Hello', 'Helvetica', 10));

        $rendered = $this->internalPage($page)->getContents()->render();

        self::assertStringContainsString('(Badge) Tj', $rendered);
        self::assertStringContainsString('(Title) Tj', $rendered);
        self::assertStringContainsString('(Callout) Tj', $rendered);
        self::assertStringContainsString('(Flow text) Tj', $rendered);
        self::assertStringContainsString('(Box text) Tj', $rendered);
    }

    #[Test]
    public function it_forwards_public_page_annotation_form_and_attachment_operations(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(220, 260));
        $file = new FileSpecification(99, 'manual.txt', new EmbeddedFileStream(100, 'data'));

        $returnedPage = $page
            ->addFileAttachment(new Rect(10, 230, 10, 10), $file)
            ->addTextAnnotation(new Rect(25, 230, 10, 10), 'Text note', 'Alice')
            ->addFreeTextAnnotation(new Rect(40, 220, 40, 20), 'Free text', 'Helvetica', 10, Color::rgb(0, 0, 0))
            ->addHighlightAnnotation(new Rect(10, 205, 30, 8), Color::rgb(1, 1, 0), 'Highlight', 'Bob')
            ->addUnderlineAnnotation(new Rect(45, 205, 30, 8), Color::rgb(0, 0, 1), 'Underline', 'Bob')
            ->addStrikeOutAnnotation(new Rect(80, 205, 30, 8), Color::rgb(1, 0, 0), 'Strike', 'Bob')
            ->addSquigglyAnnotation(new Rect(115, 205, 30, 8), Color::rgb(0, 1, 0), 'Squiggle', 'Bob')
            ->addStampAnnotation(new Rect(150, 200, 25, 12))
            ->addSquareAnnotation(new Rect(10, 180, 20, 20), Color::rgb(0, 0, 0), Color::rgb(1, 0, 0), 'Square', 'Bob', AnnotationBorderStyle::dashed())
            ->addCircleAnnotation(new Rect(35, 180, 20, 20), Color::rgb(0, 0, 0), Color::rgb(0, 1, 0), 'Circle', 'Bob', AnnotationBorderStyle::solid())
            ->addInkAnnotation(new Rect(60, 180, 30, 20), [[[60.0, 180.0], [70.0, 190.0]]], Color::rgb(0, 0, 0), 'Ink', 'Bob')
            ->addLineAnnotation(new Position(100, 180), new Position(130, 195), Color::rgb(0, 0, 0), 'Line', 'Bob', LineEndingStyle::OPEN_ARROW, LineEndingStyle::CLOSED_ARROW, 'Subject', AnnotationBorderStyle::solid())
            ->addPolyLineAnnotation([[140, 180], [150, 190], [160, 180]], Color::rgb(0, 0, 0), 'Polyline', 'Bob', LineEndingStyle::NONE, LineEndingStyle::SLASH, 'Subject', AnnotationBorderStyle::solid())
            ->addPolygonAnnotation([[170, 180], [180, 190], [190, 180]], Color::rgb(0, 0, 0), Color::rgb(1, 1, 0), 'Polygon', 'Bob', 'Subject', AnnotationBorderStyle::solid())
            ->addCaretAnnotation(new Rect(10, 160, 10, 10), 'Caret', 'Bob')
            ->addTextField('field', new Rect(10, 130, 40, 15), 'value', 'Helvetica', 10, false, Color::rgb(0, 0, 0), new FormFieldFlags(readOnly: true), 'default')
            ->addCheckbox('check', new Position(60, 137), 10, true)
            ->addRadioButton('radio', 'yes', new Position(80, 137), 10, true)
            ->addComboBox('combo', new Rect(95, 130, 40, 15), ['a' => 'A', 'b' => 'B'], 'a', 'Helvetica', 10, Color::rgb(0, 0, 0), new FormFieldFlags(editable: true), 'b')
            ->addListBox('list', new Rect(140, 120, 50, 30), ['a' => 'A', 'b' => 'B'], ['a'], 'Helvetica', 10, Color::rgb(0, 0, 0), new FormFieldFlags(multiSelect: true), ['b'])
            ->addSignatureField('signature', new Rect(10, 100, 50, 20))
            ->addPushButton(
                'push',
                'Go',
                new Rect(70, 100, 40, 20),
                'Helvetica',
                10,
                Color::rgb(0, 0, 0),
                new class () implements ButtonAction {
                    public function toPdfDictionary(): DictionaryType
                    {
                        return new DictionaryType([
                            'S' => new NameType('ResetForm'),
                        ]);
                    }
                },
            );

        $internalPage = $this->internalPage($page);
        $annotations = $internalPage->getAnnotations();

        self::assertSame($page, $returnedPage);
        self::assertGreaterThanOrEqual(18, count($annotations));

        $popupParent = $annotations[1];
        $page->addPopupAnnotation($popupParent, new Rect(195, 200, 15, 15), true);

        self::assertGreaterThanOrEqual(19, count($internalPage->getAnnotations()));
        self::assertNotNull($this->internalDocument($document)->acroForm);
    }

    /**
     * @return array<string, array{string, float, string}>
     */
    public static function standardProfileProvider(): array
    {
        return [
            'PDF 1.0' => ['pdf10', 1.0, "%PDF-1.0\n%\xE2\xE3\xCF\xD3\n"],
            'PDF 1.1' => ['pdf11', 1.1, "%PDF-1.1\n%\xE2\xE3\xCF\xD3\n"],
            'PDF 1.2' => ['pdf12', 1.2, "%PDF-1.2\n%\xE2\xE3\xCF\xD3\n"],
            'PDF 1.3' => ['pdf13', 1.3, "%PDF-1.3\n%\xE2\xE3\xCF\xD3\n"],
            'PDF 1.4' => ['pdf14', 1.4, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"],
            'PDF 1.5' => ['pdf15', 1.5, "%PDF-1.5\n%\xE2\xE3\xCF\xD3\n"],
            'PDF 1.6' => ['pdf16', 1.6, "%PDF-1.6\n%\xE2\xE3\xCF\xD3\n"],
            'PDF 1.7' => ['pdf17', 1.7, "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n"],
            'PDF 2.0' => ['pdf20', 2.0, "%PDF-2.0\n%\xE2\xE3\xCF\xD3\n"],
        ];
    }

    private function internalDocument(Document $document): Document
    {
        return $document;
    }

    private function internalPage(Page $page): Page
    {
        return $page;
    }

    private function writeDocument(Document $document): string
    {
        $stream = fopen('php://temp', 'w+b');

        self::assertNotFalse($stream);

        $writtenOutput = false;

        try {
            $document->writeToStream($stream);
            rewind($stream);

            $writtenOutput = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        self::assertNotFalse($writtenOutput);

        return $writtenOutput;
    }
}
