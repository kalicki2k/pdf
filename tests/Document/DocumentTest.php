<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Page;

use function Kalle\Pdf\Document\setDocumentRandomBytesShouldThrow;

use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsLeaderStyle;
use Kalle\Pdf\Layout\TableOfContentsOptions;
use Kalle\Pdf\Layout\TableOfContentsPlacement;
use Kalle\Pdf\Layout\TableOfContentsStyle;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    private const FLOAT_DELTA = 0.0001;

    #[Test]
    public function it_initializes_base_objects_for_pdf_1_0(): void
    {
        $document = new Document(profile: Profile::standard(1.0));

        self::assertSame(1, $document->catalog->id);
        self::assertSame(2, $document->pages->id);
        self::assertSame(3, $document->info->id);
        self::assertSame([1, 2, 3], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_creates_a_standard_pdf_profile(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        self::assertSame('standard', $document->getProfile()->name());
        self::assertSame(1.4, $document->getProfile()->version());
        self::assertSame(1.4, $document->getVersion());
    }

    #[Test]
    public function it_accepts_a_pdf_a_profile(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        self::assertSame('PDF/A-2u', $document->getProfile()->name());
        self::assertSame(1.7, $document->getVersion());
    }

    #[Test]
    public function it_accepts_a_pdf_a_2b_profile(): void
    {
        $document = new Document(profile: Profile::pdfA2b());

        self::assertSame('PDF/A-2b', $document->getProfile()->name());
        self::assertSame(1.7, $document->getVersion());
    }

    #[Test]
    public function it_accepts_a_pdf_a_3b_profile(): void
    {
        $document = new Document(profile: Profile::pdfA3b());

        self::assertSame('PDF/A-3b', $document->getProfile()->name());
        self::assertSame(1.7, $document->getVersion());
    }

    #[Test]
    public function it_accepts_a_pdf_a_3u_profile(): void
    {
        $document = new Document(profile: Profile::pdfA3u());

        self::assertSame('PDF/A-3u', $document->getProfile()->name());
        self::assertSame(1.7, $document->getVersion());
    }

    #[Test]
    public function it_rejects_encryption_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow encryption.');

        $document->encrypt(new EncryptionOptions('user', 'owner'));
    }

    #[Test]
    public function it_rejects_encryption_for_pdf_a_2b(): void
    {
        $document = new Document(profile: Profile::pdfA2b());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2b does not allow encryption.');

        $document->encrypt(new EncryptionOptions('user', 'owner'));
    }

    #[Test]
    public function it_rejects_attachments_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow embedded file attachments.');

        $document->addAttachment('demo.txt', 'hello');
    }

    #[Test]
    public function it_rejects_layers_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow optional content groups (layers).');

        $document->addLayer('Draft');
    }

    #[Test]
    public function it_rejects_acroform_fields_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage(100.0, 200.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow AcroForm fields in the current implementation.');

        $page->addTextField('customer_name', new Rect(10, 20, 80, 12), 'Ada', 'Helvetica', 12);
    }

    #[Test]
    public function it_registers_an_icc_profile_stream_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        $objectIds = array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        );

        self::assertSame([1, 2, 5, 3, 4], $objectIds);
        self::assertNotNull($document->getPdfAOutputIntentProfile());
    }

    #[Test]
    public function it_registers_an_icc_profile_stream_for_pdf_a_2b(): void
    {
        $document = new Document(profile: Profile::pdfA2b());

        $objectIds = array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        );

        self::assertSame([1, 2, 5, 3, 4], $objectIds);
        self::assertNotNull($document->getPdfAOutputIntentProfile());
    }

    #[Test]
    public function it_registers_an_icc_profile_stream_for_pdf_a_3b(): void
    {
        $document = new Document(profile: Profile::pdfA3b());

        $objectIds = array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        );

        self::assertSame([1, 2, 5, 3, 4], $objectIds);
        self::assertNotNull($document->getPdfAOutputIntentProfile());
    }

    #[Test]
    public function it_allows_attachments_for_pdf_a_3b_and_marks_them_as_associated_files(): void
    {
        $document = new Document(profile: Profile::pdfA3b());

        $document->addAttachment('data.xml', '<root/>', 'Machine-readable source', 'application/xml');

        self::assertCount(1, $document->getAttachments());
        self::assertStringContainsString('/AFRelationship /Data', $document->getAttachments()[0]->render());
    }

    #[Test]
    public function it_allows_attachments_for_pdf_a_3u_and_marks_them_as_associated_files(): void
    {
        $document = new Document(profile: Profile::pdfA3u());

        $document->addAttachment('data.xml', '<root/>', 'Machine-readable source', 'application/xml');

        self::assertCount(1, $document->getAttachments());
        self::assertStringContainsString('/AFRelationship /Data', $document->getAttachments()[0]->render());
    }

    #[Test]
    public function it_allows_attachments_for_pdf_a_4f_and_marks_them_as_associated_files(): void
    {
        $document = new Document(profile: Profile::pdfA4f());

        $document->addAttachment('data.xml', '<root/>', 'Machine-readable source', 'application/xml');

        self::assertCount(1, $document->getAttachments());
        self::assertStringContainsString('/AFRelationship /Data', $document->getAttachments()[0]->render());
    }

    #[Test]
    public function it_rejects_standard_fonts_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Profile PDF/A-2u does not allow PDF standard fonts like 'Helvetica'. Register an embedded Unicode font instead.");

        $document->registerFont('Helvetica');
    }

    #[Test]
    public function it_rejects_standard_fonts_for_pdf_a_2b(): void
    {
        $document = new Document(profile: Profile::pdfA2b());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Profile PDF/A-2b does not allow PDF standard fonts like 'Helvetica'. Register an embedded Unicode font instead.");

        $document->registerFont('Helvetica');
    }

    #[Test]
    public function it_accepts_embedded_unicode_fonts_for_pdf_a_2b(): void
    {
        $document = new Document(profile: Profile::pdfA2b());

        $document->registerFont('NotoSans-Regular');

        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame('NotoSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_accepts_embedded_unicode_fonts_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        $document->registerFont('NotoSans-Regular');

        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame('NotoSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_initializes_structure_objects_for_pdf_1_4_and_above(): void
    {
        $document = new Document(profile: Profile::standard(1.4), language: 'de-DE');

        self::assertSame([1, 2, 3, 4], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringNotContainsString('/StructTreeRoot', $document->catalog->render());
    }

    #[Test]
    public function it_assigns_object_ids_to_added_fonts_and_pages(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $returnedDocument = $document->registerFont('Helvetica');
        $page = $document->addPage(100.0, 200.0);

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->getFonts());
        self::assertSame(4, $document->getFonts()[0]->id);
        self::assertSame(5, $page->id);
        self::assertSame(6, $page->contents->id);
        self::assertSame(7, $page->resources->id);
        self::assertSame([1, 2, 3, 4, 5, 7, 6, 8], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_registers_standard_fonts_via_the_explicit_register_font_api(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $returnedDocument = $document->registerFont('Helvetica');

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->getFonts());
        self::assertSame('Helvetica', $document->getFonts()[0]->getBaseFont());
    }

    #[Test]
    public function it_can_find_registered_fonts_by_their_base_font_name(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');

        self::assertSame($document->getFonts()[0], $document->getFontByBaseFont('Helvetica'));
        self::assertNull($document->getFontByBaseFont('Courier'));
    }

    #[Test]
    public function it_uses_distinct_defaults_for_creator_producer_and_creator_tool_metadata(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        self::assertSame('kalle/pdf', $document->getCreator());
        self::assertStringStartsWith('kalle/pdf', $document->getProducer());
        self::assertSame('kalle/pdf', $document->getCreatorTool());
    }

    #[Test]
    public function it_allows_custom_creator_producer_and_creator_tool_metadata(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            creator: 'Acme Invoice Service',
            creatorTool: 'Acme Backoffice',
        );

        $returnedDocument = $document->setProducer('kalle/pdf 1.0');

        self::assertSame($document, $returnedDocument);
        self::assertSame('Acme Invoice Service', $document->getCreator());
        self::assertSame('kalle/pdf 1.0', $document->getProducer());
        self::assertSame('Acme Backoffice', $document->getCreatorTool());
    }

    #[Test]
    public function it_falls_back_to_the_package_name_for_an_empty_creator_metadata_value(): void
    {
        $document = new Document(profile: Profile::standard(1.4), creator: '');

        self::assertSame('kalle/pdf', $document->getCreator());
    }

    #[Test]
    public function it_allows_updating_the_creator_metadata(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $returnedDocument = $document->setCreator('Acme Renderer');

        self::assertSame($document, $returnedDocument);
        self::assertSame('Acme Renderer', $document->getCreator());
    }

    #[Test]
    public function it_rejects_an_empty_creator_metadata_value(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Creator must not be empty.');

        $document->setCreator('');
    }

    #[Test]
    public function it_rejects_an_empty_producer_metadata_value(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Producer must not be empty.');

        $document->setProducer('');
    }

    #[Test]
    public function it_falls_back_to_the_package_name_for_an_empty_creator_tool_metadata_value(): void
    {
        $document = new Document(profile: Profile::standard(1.4), creatorTool: '');

        self::assertSame('kalle/pdf', $document->getCreatorTool());
    }

    #[Test]
    public function it_allows_updating_the_creator_tool_metadata(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $returnedDocument = $document->setCreatorTool('Acme Backoffice');

        self::assertSame($document, $returnedDocument);
        self::assertSame('Acme Backoffice', $document->getCreatorTool());
    }

    #[Test]
    public function it_rejects_an_empty_creator_tool_metadata_value(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Creator tool must not be empty.');

        $document->setCreatorTool('');
    }

    #[Test]
    public function it_uses_a_pdf_1_0_compatible_default_encoding_for_standard_fonts(): void
    {
        $document = new Document(profile: Profile::standard(1.0));

        $document->registerFont('Helvetica');

        self::assertStringContainsString('/Encoding ', $document->getFonts()[0]->render());
        self::assertStringContainsString('/BaseEncoding /StandardEncoding', $document->render());
    }

    #[Test]
    public function it_keeps_the_win_ansi_default_for_newer_pdf_versions(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $document->registerFont('Helvetica');

        self::assertStringContainsString('/Encoding /WinAnsiEncoding', $document->getFonts()[0]->render());
    }

    #[Test]
    public function it_still_validates_explicitly_provided_encodings_against_the_pdf_version(): void
    {
        $document = new Document(profile: Profile::standard(1.0));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Encoding 'WinAnsiEncoding' is not allowed in PDF 1.0.");

        $document->registerFont('Helvetica', encoding: 'WinAnsiEncoding');
    }

    #[Test]
    public function it_adds_a_page_from_a_named_page_size(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $page = $document->addPage(PageSize::A5());

        self::assertEqualsWithDelta(419.5275590551, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(595.2755905512, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_adds_attachments_as_indirect_objects(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');

        self::assertSame([1, 2, 5, 4, 3, 6], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_rejects_empty_attachment_filenames(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attachment filename must not be empty.');

        $document->addAttachment('', 'hello');
    }

    #[Test]
    public function it_can_add_an_attachment_from_a_file_and_look_it_up_by_filename(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-attachment-');
        self::assertNotFalse($path);
        file_put_contents($path, 'attachment-data');

        try {
            $document = new Document(profile: Profile::standard(1.4));

            $returnedDocument = $document->addAttachmentFromFile(
                $path,
                filename: 'custom.txt',
                description: 'Imported attachment',
                mimeType: 'text/plain',
            );

            self::assertSame($document, $returnedDocument);
            self::assertCount(1, $document->getAttachments());
            self::assertSame($document->getAttachments()[0], $document->getAttachment('custom.txt'));
            self::assertNull($document->getAttachment('missing.txt'));
            self::assertSame('custom.txt', $document->getAttachments()[0]->getFilename());
            self::assertStringContainsString('/Desc (Imported attachment)', $document->getAttachments()[0]->render());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_rejects_missing_attachment_files(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $path = sys_get_temp_dir() . '/pdf-missing-' . uniqid('', true) . '.txt';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Attachment file '$path' does not exist.");

        $document->addAttachmentFromFile($path);
    }

    #[Test]
    public function it_rejects_unreadable_attachment_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-unreadable-');
        self::assertNotFalse($path);
        file_put_contents($path, 'attachment-data');
        chmod($path, 0000);

        try {
            $document = new Document(profile: Profile::standard(1.4));

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Attachment file '$path' could not be read.");

            $document->addAttachmentFromFile($path);
        } finally {
            chmod($path, 0600);
            @unlink($path);
        }
    }

    #[Test]
    public function it_rejects_unreadable_font_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-font-');
        self::assertNotFalse($path);
        file_put_contents($path, 'font-data');
        chmod($path, 0000);

        try {
            $document = new Document(profile: Profile::standard(1.4));

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Unable to read font file '$path'.");

            $document->registerFont('CustomFont', 'Type1', 'WinAnsiEncoding', false, $path);
        } finally {
            chmod($path, 0600);
            @unlink($path);
        }
    }

    #[Test]
    public function it_creates_an_optional_font_parser_for_readable_font_files(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $method = new \ReflectionMethod($document, 'createOptionalFontParser');

        $parser = $method->invoke($document, 'assets/fonts/NotoSans-Regular.ttf');

        self::assertInstanceOf(OpenTypeFontParser::class, $parser);
    }

    #[Test]
    public function it_falls_back_to_a_deterministic_document_id_when_random_bytes_fails(): void
    {
        setDocumentRandomBytesShouldThrow(true);

        try {
            $document = new Document(profile: Profile::standard(1.4));
            $documentId = $document->getDocumentId();

            self::assertCount(2, $documentId);
            self::assertSame($documentId[0], $documentId[1]);
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $documentId[0]);
        } finally {
            setDocumentRandomBytesShouldThrow(false);
        }
    }

    #[Test]
    public function it_adds_a_page_from_the_a00_special_case(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $page = $document->addPage(PageSize::A00());

        self::assertEqualsWithDelta(3370.3937007874, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(4767.874015748, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_adds_a_page_from_the_b_series(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $page = $document->addPage(PageSize::B4());

        self::assertEqualsWithDelta(708.6614173228, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(1000.6299212598, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_adds_a_page_from_the_c_series(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $page = $document->addPage(PageSize::C5());

        self::assertEqualsWithDelta(459.2125984252, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(649.1338582677, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_adds_a_landscape_page_from_a_named_page_size(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $page = $document->addPage(PageSize::A4()->landscape());

        self::assertEqualsWithDelta(841.8897637795, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(595.2755905512, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_applies_header_and_footer_callbacks_to_new_pages(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $document
            ->addHeader(static function (Page $page, int $pageNumber): void {
                $page->addText("Header $pageNumber", new Position(10, 90), 'Helvetica', 10);
            })
            ->addFooter(static function (Page $page, int $pageNumber): void {
                $page->addText("Footer $pageNumber", new Position(10, 10), 'Helvetica', 10);
            });

        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);
        $document->render();

        self::assertStringContainsString('(Header 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Footer 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Header 2) Tj', $secondPage->contents->render());
        self::assertStringContainsString('(Footer 2) Tj', $secondPage->contents->render());
    }

    #[Test]
    public function it_rejects_structured_content_on_pdf_versions_below_1_4(): void
    {
        $document = new Document(profile: Profile::standard(1.3));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Structured content requires PDF version 1.4 or higher.');

        $document->ensureStructureEnabled();
    }

    #[Test]
    public function it_applies_header_and_footer_callbacks_to_overflow_pages(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $document
            ->addHeader(static function (Page $page, int $pageNumber): void {
                $page->addText("Header $pageNumber", new Position(10, 50), 'Helvetica', 10);
            })
            ->addFooter(static function (Page $page, int $pageNumber): void {
                $page->addText("Footer $pageNumber", new Position(10, 5), 'Helvetica', 10);
            });

        $firstPage = $document->addPage(100, 60);
        $frame = $firstPage->createTextFrame(new Position(10, 40), 80, 10);
        $frame->addParagraph(str_repeat('Wort ', 80), 'Helvetica', 12, new ParagraphOptions(structureTag: StructureTag::Paragraph));
        $lastPage = $document->pages->pages[array_key_last($document->pages->pages)];
        $lastPageNumber = count($document->pages->pages);
        $document->render();

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertStringContainsString('(Header 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Footer 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString("(Header $lastPageNumber) Tj", $lastPage->contents->render());
        self::assertStringContainsString("(Footer $lastPageNumber) Tj", $lastPage->contents->render());
    }

    #[Test]
    public function it_adds_footer_page_numbers_with_total_page_count(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $document->addPage(100, 100);
        $document->addPage(100, 100);

        $document->addPageNumbers(new Position(10, 10));
        $document->render();

        self::assertStringContainsString('(Seite 1 von 2) Tj', $document->pages->pages[0]->contents->render());
        self::assertStringContainsString('(Seite 2 von 2) Tj', $document->pages->pages[1]->contents->render());
    }

    #[Test]
    public function it_adds_header_page_numbers_with_a_custom_template(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $document->addPage(100, 100);
        $document->addPage(100, 100);
        $document->addPage(100, 100);

        $document->addPageNumbers(new Position(10, 90), 'Helvetica', 10, 'Seite {{page}} / {{pages}}', false);
        $document->render();

        self::assertStringContainsString('(Seite 1 / 3) Tj', $document->pages->pages[0]->contents->render());
        self::assertStringContainsString('(Seite 3 / 3) Tj', $document->pages->pages[2]->contents->render());
    }

    #[Test]
    public function it_can_use_logical_page_numbers_for_visible_page_numbering(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $coverPage = $document->addPage(100, 100);
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        $document->excludePageFromNumbering($coverPage);
        $document->addPageNumbers(new Position(10, 10), useLogicalPageNumbers: true);
        $document->render();

        self::assertStringNotContainsString('(Seite', $coverPage->contents->render());
        self::assertStringContainsString('(Seite 1 von 2) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Seite 2 von 2) Tj', $secondPage->contents->render());
    }

    #[Test]
    public function it_rejects_empty_page_number_templates(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page number template must not be empty.');

        $document->addPageNumbers(new Position(10, 10), template: '');
    }

    #[Test]
    public function it_rejects_non_positive_page_number_sizes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page number size must be greater than zero.');

        $document->addPageNumbers(new Position(10, 10), size: 0);
    }

    #[Test]
    public function it_adds_a_table_of_contents_from_existing_outlines(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        $tocPage = $document->addTableOfContents(PageSize::A6(), new TableOfContentsOptions(title: 'Inhalt', baseFont: 'Helvetica', titleSize: 16, entrySize: 10, margin: 10));

        self::assertSame($document->pages->pages[array_key_last($document->pages->pages)], $tocPage);
        self::assertStringContainsString('(Inhalt) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(Erste Seite) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(1) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(Zweite Seite) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(2) Tj', $tocPage->contents->render());
        self::assertStringContainsString('/Dests << /toc-page-5 [5 0 R /Fit] /toc-page-8 [8 0 R /Fit] >>', $document->render());
    }

    #[Test]
    public function it_rejects_table_of_contents_pages_without_positive_content_width(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100.0, 100.0);
        $document->addOutline('Erste Seite', $page);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents content width must be greater than zero.');

        $document->addTableOfContents(
            PageSize::custom(20.0, 100.0),
            new TableOfContentsOptions(title: 'Inhalt', baseFont: 'Helvetica', titleSize: 16, entrySize: 10, margin: 10),
        );
    }

    #[Test]
    public function it_skips_table_of_contents_entries_when_the_target_page_has_no_resolved_page_number(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $validPage = $document->addPage(100, 100);

        $foreignDocument = new Document(profile: Profile::standard(1.4));
        $foreignDocument->addPage(100, 100);
        $foreignPage = $foreignDocument->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $validPage)
            ->addOutline('Fremde Seite', $foreignPage);

        $tocPage = $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(title: 'Inhalt', baseFont: 'Helvetica', titleSize: 16, entrySize: 10, margin: 10),
        );

        self::assertStringContainsString('(Erste Seite) Tj', $tocPage->contents->render());
        self::assertStringNotContainsString('(Fremde Seite) Tj', $tocPage->contents->render());
        self::assertStringContainsString('/Dests << /toc-page-5 [5 0 R /Fit] >>', $document->render());
    }

    #[Test]
    public function it_can_move_the_table_of_contents_to_the_start(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        $tocPage = $document->addTableOfContents(PageSize::A6(), new TableOfContentsOptions(title: 'Inhalt', baseFont: 'Helvetica', titleSize: 16, entrySize: 10, margin: 10, placement: TableOfContentsPlacement::start()));

        self::assertSame($tocPage, $document->pages->pages[0]);
        self::assertSame($firstPage, $document->pages->pages[1]);
        self::assertSame($secondPage, $document->pages->pages[2]);
    }

    #[Test]
    public function it_numbers_entries_correctly_when_a_multi_page_table_of_contents_moves_to_the_start(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $firstContentPage = null;

        foreach (range('A', 'Z') as $label) {
            $page = $document->addPage(100, 100);
            $firstContentPage ??= $page;
            $document->addOutline("Eintrag $label", $page);
        }

        $document->addTableOfContents(PageSize::A7(), new TableOfContentsOptions(title: 'Inhalt', baseFont: 'Helvetica', titleSize: 16, entrySize: 10, margin: 10, placement: TableOfContentsPlacement::start()));

        $firstContentPageIndex = array_search($firstContentPage, $document->pages->pages, true);
        self::assertIsInt($firstContentPageIndex);
        self::assertGreaterThan(1, $firstContentPageIndex);

        $tocPages = array_slice($document->pages->pages, 0, $firstContentPageIndex);
        $tocContents = implode('', array_map(
            static fn (Page $page): string => $page->contents->render(),
            $tocPages,
        ));

        self::assertStringContainsString('(' . ($firstContentPageIndex + 1) . ') Tj', $tocContents);
        self::assertStringContainsString('(' . ($firstContentPageIndex + 26) . ') Tj', $tocContents);
        self::assertStringNotContainsString('(1) Tj', $tocContents);
        self::assertStringNotContainsString('(2) Tj', $tocContents);
    }

    #[Test]
    public function it_applies_header_page_numbers_after_moving_the_table_of_contents_to_the_start(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $document->addHeader(static function (Page $page, int $pageNumber): void {
            $page->addText("Header $pageNumber", new Position(10, 90), 'Helvetica', 10);
        });

        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        $tocPage = $document->addTableOfContents(PageSize::A6(), new TableOfContentsOptions(title: 'Inhalt', baseFont: 'Helvetica', titleSize: 16, entrySize: 10, margin: 10, placement: TableOfContentsPlacement::start()));
        $document->render();

        self::assertStringContainsString('(Header 1) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(Header 2) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Header 3) Tj', $secondPage->contents->render());
    }

    #[Test]
    public function it_can_insert_the_table_of_contents_after_the_first_page(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $coverPage = $document->addPage(100, 100);
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        $tocPage = $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 10,
                placement: TableOfContentsPlacement::afterPage(1),
            ),
        );

        self::assertSame($coverPage, $document->pages->pages[0]);
        self::assertSame($tocPage, $document->pages->pages[1]);
        self::assertSame($firstPage, $document->pages->pages[2]);
        self::assertSame($secondPage, $document->pages->pages[3]);
        self::assertStringContainsString('(3) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(4) Tj', $tocPage->contents->render());
    }

    #[Test]
    public function it_can_append_the_table_of_contents_and_keep_logical_numbers_for_content_pages(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        $tocPage = $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 10,
            ),
        );

        self::assertSame($firstPage, $document->pages->pages[0]);
        self::assertSame($secondPage, $document->pages->pages[1]);
        self::assertSame($tocPage, $document->pages->pages[2]);
        self::assertStringContainsString('(1) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(2) Tj', $tocPage->contents->render());
    }

    #[Test]
    public function it_can_append_the_table_of_contents_and_use_logical_numbers_at_the_end(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $coverPage = $document->addPage(100, 100);
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);
        $thirdPage = $document->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage)
            ->addOutline('Dritte Seite', $thirdPage);

        $tocPage = $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 10,
                useLogicalPageNumbers: true,
            ),
        );

        self::assertSame($coverPage, $document->pages->pages[0]);
        self::assertSame($firstPage, $document->pages->pages[1]);
        self::assertSame($secondPage, $document->pages->pages[2]);
        self::assertSame($thirdPage, $document->pages->pages[3]);
        self::assertSame($tocPage, $document->pages->pages[4]);
        self::assertStringContainsString('(2) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(3) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(4) Tj', $tocPage->contents->render());
    }

    #[Test]
    public function it_can_use_logical_page_numbers_for_a_table_of_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $coverPage = $document->addPage(100, 100);
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);
        $thirdPage = $document->addPage(100, 100);

        $document->excludePageFromNumbering($coverPage);
        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage)
            ->addOutline('Dritte Seite', $thirdPage);

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

        $tocContents = $tocPage->contents->render();

        self::assertStringContainsString('(2) Tj', $tocContents);
        self::assertStringContainsString('(3) Tj', $tocContents);
        self::assertStringContainsString('(4) Tj', $tocContents);
        self::assertStringNotContainsString('(5) Tj', $tocContents);
    }

    #[Test]
    public function it_can_render_a_table_of_contents_without_leader_characters(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 100);
        $document->addOutline('Erste Seite', $page);

        $tocPage = $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 10,
                style: new TableOfContentsStyle(leaderStyle: TableOfContentsLeaderStyle::NONE),
            ),
        );

        self::assertStringNotContainsString('(....', $tocPage->contents->render());
    }

    #[Test]
    public function it_can_render_a_table_of_contents_with_dash_leaders(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 100);
        $document->addOutline('Erste Seite', $page);

        $tocPage = $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 10,
                style: new TableOfContentsStyle(leaderStyle: TableOfContentsLeaderStyle::DASHES),
            ),
        );

        self::assertStringContainsString('(---', $tocPage->contents->render());
    }

    #[Test]
    public function it_truncates_table_of_contents_titles_to_an_ellipsis_when_even_the_short_form_barely_fits(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 100);
        $document->addOutline('ABCDEFGHIJKLMN', $page);

        $tocPage = $document->addTableOfContents(
            PageSize::A7(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 100,
                placement: TableOfContentsPlacement::start(),
            ),
        );

        $tocContents = $tocPage->contents->render();

        self::assertStringContainsString('(...) Tj', $tocContents);
        self::assertStringNotContainsString('(ABCDE...) Tj', $tocContents);
    }

    #[Test]
    public function it_truncates_table_of_contents_titles_with_a_visible_prefix_when_space_allows(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 100);
        $document->addOutline('ABCDEFGHIJKLMN', $page);

        $tocPage = $document->addTableOfContents(
            PageSize::A7(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 80,
                placement: TableOfContentsPlacement::start(),
            ),
        );

        $tocContents = $tocPage->contents->render();

        self::assertStringContainsString('(ABCD...) Tj', $tocContents);
        self::assertStringNotContainsString('(ABCDEFGHIJKLMN) Tj', $tocContents);
    }

    #[Test]
    public function it_rejects_negative_table_of_contents_style_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents entry spacing must be zero or greater.');

        new TableOfContentsStyle(entrySpacing: -1.0);
    }

    #[Test]
    public function it_rejects_an_out_of_bounds_table_of_contents_insertion_page(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(100, 100);
        $document->addOutline('Erste Seite', $page);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents insertion page 2 is out of bounds for a document with 1 pages.');

        $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 10,
                placement: TableOfContentsPlacement::afterPage(2),
            ),
        );
    }

    #[Test]
    public function it_rejects_a_table_of_contents_without_outline_entries(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents requires at least one outline entry.');

        $document->addTableOfContents(PageSize::A6(), new TableOfContentsOptions(title: 'Inhalt', baseFont: 'Helvetica', titleSize: 16, entrySize: 10, margin: 10));
    }

    #[Test]
    public function it_registers_outline_objects_and_links_them_to_pages(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $firstPage = $document->addPage(100.0, 200.0);
        $secondPage = $document->addPage(100.0, 200.0);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        self::assertSame([1, 2, 10, 11, 12, 3, 4, 6, 5, 7, 9, 8, 13], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringContainsString('10 0 obj' . "\n" . '<< /Type /Outlines /Count 2 /First 11 0 R /Last 12 0 R >>', $document->render());
        self::assertStringContainsString('11 0 obj' . "\n" . '<< /Title (Erste Seite) /Parent 10 0 R /Dest [4 0 R /Fit] /Next 12 0 R >>', $document->render());
        self::assertStringContainsString('12 0 obj' . "\n" . '<< /Title (Zweite Seite) /Parent 10 0 R /Dest [7 0 R /Fit] /Prev 11 0 R >>', $document->render());
    }

    #[Test]
    public function it_rejects_empty_outline_titles(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100.0, 200.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Outline title must not be empty.');

        $document->addOutline('', $page);
    }

    #[Test]
    public function it_registers_named_destinations_on_the_catalog(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100.0, 200.0);

        $document->addDestination('table-demo', $page);

        self::assertStringContainsString('/Dests << /table-demo [4 0 R /Fit] >>', $document->render());
    }

    #[Test]
    public function it_rejects_empty_destination_names(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100.0, 200.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination name must not be empty.');

        $document->addDestination('', $page);
    }

    #[Test]
    public function it_rejects_invalid_destination_names(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100.0, 200.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination name may contain only letters, numbers, dots, underscores and hyphens.');

        $document->addDestination('table demo', $page);
    }

    #[Test]
    public function it_reuses_optional_content_groups_for_the_same_layer_name(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $firstLayer = $document->addLayer('Draft', visibleByDefault: false);
        $secondLayer = $document->ensureOptionalContentGroup('Draft');

        self::assertSame($firstLayer, $secondLayer);
        self::assertSame('Draft', $firstLayer->getName());
        self::assertFalse($firstLayer->isVisibleByDefault());
        self::assertSame([$firstLayer], $document->getOptionalContentGroups());
    }

    #[Test]
    public function it_rejects_empty_optional_content_group_names(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Optional content group name must not be empty.');

        $document->ensureOptionalContentGroup('');
    }

    #[Test]
    public function it_rejects_combining_page_size_and_explicit_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Height must not be provided when using a PageSize.');

        $document->addPage(PageSize::A4(), 100.0);
    }

    #[Test]
    public function it_registers_unicode_fonts_together_with_their_descendant_font_objects(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $returnedDocument = $document->registerFont('NotoSansCJKsc-Regular', 'CIDFontType2', unicode: true);

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame(9, $document->getFonts()[0]->id);
        self::assertSame(7, $document->getFonts()[0]->descendantFont->id);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_registers_embedded_fonts_via_their_direct_font_names(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $document
            ->registerFont('NotoSans-Regular')
            ->registerFont('NotoSansCJKsc-Regular');

        self::assertCount(2, $document->getFonts());
        self::assertSame('NotoSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
        self::assertSame('NotoSansCJKsc-Regular', $document->getFonts()[1]->getBaseFont());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[1]);
        self::assertNotNull($document->getFonts()[1]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[1]->descendantFont->cidToGidMap);
        self::assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16],
            array_map(
                static fn (object $object): int => $object->id,
                $document->getDocumentObjects(),
            ),
        );
    }

    #[Test]
    public function it_registers_the_noto_sans_font_as_an_embedded_font(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $document->registerFont('NotoSans-Regular');

        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame('NotoSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_registers_an_embedded_font_by_its_direct_font_name(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $document->registerFont('NotoSans-Regular');

        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame('NotoSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_registers_serif_and_mono_fonts_as_embedded_fonts_by_their_direct_names(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $document
            ->registerFont('NotoSerif-Regular')
            ->registerFont('NotoSansMono-Regular');

        self::assertCount(2, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[1]);
        self::assertSame('NotoSerif-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertSame('NotoSansMono-Regular', $document->getFonts()[1]->getBaseFont());
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[1]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
        self::assertNotNull($document->getFonts()[1]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_can_use_a_document_specific_font_configuration(): void
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

        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame('CustomSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertSame($document->getFontConfig(), [
            [
                'baseFont' => 'CustomSans-Regular',
                'path' => 'assets/fonts/NotoSans-Regular.ttf',
                'unicode' => true,
                'subtype' => 'CIDFontType2',
                'encoding' => 'Identity-H',
            ],
        ]);
    }

    #[Test]
    public function it_creates_and_reuses_a_single_acro_form_instance(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $firstAcroForm = $document->ensureAcroForm();
        $secondAcroForm = $document->ensureAcroForm();

        self::assertSame($firstAcroForm, $secondAcroForm);
        self::assertSame($firstAcroForm, $document->acroForm);
        self::assertContains($firstAcroForm, $document->getDocumentObjects());
    }

    #[Test]
    public function it_sets_encryption_options_and_caches_the_built_security_handler_data(): void
    {
        $document = new Document(profile: Profile::standard(1.7));
        $options = new EncryptionOptions('user-secret', 'owner-secret');

        $returnedDocument = $document->encrypt($options);
        $firstSecurityHandlerData = $document->getSecurityHandlerData();
        $secondSecurityHandlerData = $document->getSecurityHandlerData();

        self::assertSame($document, $returnedDocument);
        self::assertSame($options, $document->getEncryptionOptions());
        self::assertNotNull($document->getEncryptionProfile());
        self::assertNotNull($document->encryptDictionary);
        self::assertNotNull($firstSecurityHandlerData);
        self::assertSame($firstSecurityHandlerData, $secondSecurityHandlerData);
    }

    #[Test]
    public function it_normalizes_keywords_uniquely_while_preserving_first_occurrence(): void
    {
        $document = new Document(profile: Profile::standard(1.0));

        $document
            ->addKeyword('  pdf ')
            ->addKeyword('testing')
            ->addKeyword('pdf')
            ->addKeyword(' testing ');

        self::assertSame(['pdf', 'testing'], $document->getKeywords());
    }

    #[Test]
    public function it_discards_empty_keywords_after_trimming(): void
    {
        $document = new Document(profile: Profile::standard(1.4), title: 'Spec');

        $document
            ->addKeyword(' ')
            ->addKeyword('pdf')
            ->addKeyword('   ');

        self::assertSame(['pdf'], $document->getKeywords());
        self::assertStringContainsString('/Keywords (pdf)', $document->render());
        self::assertStringNotContainsString('/Keywords (,', $document->render());
        self::assertStringNotContainsString('<rdf:li></rdf:li>', $document->render());
        self::assertStringContainsString('<pdf:Keywords>pdf</pdf:Keywords>', $document->render());
    }

    #[Test]
    public function it_adds_structure_elements_and_links_them_to_the_document_root(): void
    {
        $document = new Document(profile: Profile::standard(1.4));

        $result = $document->addStructElem(StructureTag::Paragraph, 42);

        self::assertSame($document, $result);
        self::assertSame([1, 2, 4, 5, 6, 7, 3, 8], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringContainsString('6 0 obj' . "\n" . '<< /Type /StructElem /S /Document /K [7 0 R] >>', $document->render());
        self::assertStringContainsString('7 0 obj' . "\n" . '<< /Type /StructElem /S /P /P 6 0 R /K [] >>', $document->render());
    }

    #[Test]
    public function it_can_create_nested_structure_elements(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->addPage();

        $list = $document->createStructElem(StructureTag::List);
        $item = $document->createStructElem(StructureTag::ListItem, parent: $list);
        $label = $document->createStructElem(StructureTag::Label, 0, $document->pages->pages[0], $item);

        $rendered = $document->render();

        self::assertStringContainsString('/Type /StructElem /S /Document /K [10 0 R]', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /L /P 9 0 R /K [11 0 R]', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /LI /P 10 0 R /K [12 0 R]', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Lbl /P 11 0 R /Pg 4 0 R /K 0', $rendered);
    }

    #[Test]
    public function it_renders_a_pdf_document_with_structure_metadata_for_version_1_4(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            title: 'Spec',
            author: 'Kalle',
            subject: 'Tests',
            language: 'de-DE',
        );

        $document->addKeyword('pdf');
        $document->registerFont('Helvetica');
        $document->addPage();

        $output = $document->render();

        self::assertStringStartsWith("%PDF-1.4\n", $output);
        self::assertStringContainsString('/Title (Spec)', $output);
        self::assertStringContainsString('/Author (Kalle)', $output);
        self::assertStringContainsString('/Subject (Tests)', $output);
        self::assertStringContainsString('/Keywords (pdf)', $output);
        self::assertStringContainsString('/Metadata 8 0 R', $output);
        self::assertStringContainsString('<dc:format>application/pdf</dc:format>', $output);
        self::assertStringContainsString('<rdf:li>de-DE</rdf:li>', $output);
        self::assertStringNotContainsString('/Lang (de-DE)', $output);
        self::assertStringNotContainsString('/MarkInfo', $output);
        self::assertStringNotContainsString('/StructTreeRoot', $output);
        self::assertStringContainsString("xref\n0 9\n", $output);
        self::assertStringContainsString("trailer\n<< /Size 9\n/Root 1 0 R\n/Info 3 0 R\n/ID [<", $output);
        self::assertMatchesRegularExpression('/\/CreationDate \(D:\d{14}[+-]\d{4}\)/', $output);
        self::assertMatchesRegularExpression('/\/ModDate \(D:\d{14}[+-]\d{4}\)/', $output);
        self::assertStringEndsWith('%%EOF', $output);
    }

    #[Test]
    public function it_enables_structure_metadata_only_after_tagged_content_is_added(): void
    {
        $document = new Document(profile: Profile::standard(1.4), language: 'de-DE');
        $document->registerFont('Helvetica');
        $document->addPage()->addText('Hello', new Position(10, 20), 'Helvetica', 12, new TextOptions(structureTag: StructureTag::Paragraph));

        $output = $document->render();

        self::assertStringContainsString('/Lang (de-DE)', $output);
        self::assertStringContainsString('/Metadata 12 0 R', $output);
        self::assertStringContainsString('/StructTreeRoot 8 0 R', $output);
        self::assertStringContainsString('/StructParents 0', $output);
    }
}
