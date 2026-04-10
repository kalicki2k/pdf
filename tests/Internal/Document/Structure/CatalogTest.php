<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\Structure;

use Kalle\Pdf\Document;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Internal\Document\Structure\Catalog;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Page;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\TaggedPdf\StructureTag;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    #[Test]
    public function it_renders_a_minimal_catalog_for_pdf_1_0(): void
    {
        $document = new Document(profile: Profile::standard(1.0));
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_structure_metadata_for_pdf_1_4(): void
    {
        $document = new Document(profile: Profile::standard(1.4), language: 'de-DE');
        $document->registerFont('Helvetica');
        $document->addPage()->addText('Hello', new Position(10, 20), 'Helvetica', 12, new TextOptions(structureTag: StructureTag::Paragraph));
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n"
            . "<< /Type /Catalog /Pages 2 0 R /Metadata 12 0 R /MarkInfo << /Marked true >> /Lang (de-DE) /StructTreeRoot 8 0 R >>\n"
            . "endobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_pdf_ua_viewer_preferences_and_structure_metadata(): void
    {
        $document = $this->createPdfUaTestDocument();
        $document->addPage()->addText('Hello', new Position(10, 20), self::pdfUaRegularFont(), 12, new TextOptions(structureTag: StructureTag::Paragraph));
        $catalog = new Catalog(1, $document);

        $rendered = $catalog->render();

        self::assertStringStartsWith("1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata ", $rendered);
        self::assertStringContainsString('/ViewerPreferences << /DisplayDocTitle true >>', $rendered);
        self::assertStringContainsString('/MarkInfo << /Marked true >>', $rendered);
        self::assertStringContainsString('/Lang (de-DE)', $rendered);
        self::assertStringContainsString('/StructTreeRoot ', $rendered);
        self::assertStringEndsWith(">>\nendobj\n", $rendered);
    }

    #[Test]
    public function it_keeps_the_catalog_unstructured_for_pdf_1_4_without_tagged_content(): void
    {
        $document = new Document(profile: Profile::standard(1.4), language: 'de-DE');
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 4 0 R >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_outline_references_when_document_outlines_exist(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $document->addOutline('Intro', $page);
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 9 0 R /Outlines 7 0 R /PageMode /UseOutlines >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_named_destinations_when_document_destinations_exist(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $document->addDestination('table-demo', $page);
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R /Dests << /table-demo [4 0 R /Fit] >> >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_optional_content_properties_when_document_layers_exist(): void
    {
        $document = new Document(profile: Profile::standard(1.5));
        $page = $document->addPage();
        $page->layer('Notes', static function (Page $page): void {
            $page->addRectangle(new Rect(10, 20, 30, 40));
        });
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n"
            . "<< /Type /Catalog /Pages 2 0 R /Metadata 8 0 R /OCProperties << /OCGs [7 0 R] /D << /Order [7 0 R] /ON [7 0 R] >> >> >>\n"
            . "endobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_off_optional_content_groups_when_layers_are_hidden_by_default(): void
    {
        $document = new Document(profile: Profile::standard(1.5));
        $document->addLayer('Visible');
        $document->addLayer('Hidden', false);
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n"
            . "<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /OCProperties << /OCGs [4 0 R 5 0 R] /D << /Order [4 0 R 5 0 R] /ON [4 0 R] /OFF [5 0 R] >> >> >>\n"
            . "endobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_embedded_file_name_tree_when_document_attachments_exist(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n"
            . "<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles << /Names [(demo.txt) 5 0 R] >> >> >>\n"
            . "endobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_an_acro_form_reference_when_the_document_contains_form_fields(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->addPage();
        $document->ensureAcroForm();
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 8 0 R /AcroForm 7 0 R >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_a_pdf_a_output_intent_for_pdf_a_2u(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $catalog = new Catalog(1, $document);

        $rendered = $catalog->render();

        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('/S /GTS_PDFA1', $rendered);
        self::assertStringContainsString('/OutputConditionIdentifier (sRGB IEC61966-2.1)', $rendered);
        self::assertStringContainsString('/DestOutputProfile 5 0 R', $rendered);
    }

    #[Test]
    public function it_renders_a_pdf_a_output_intent_for_pdf_a_2b(): void
    {
        $document = new Document(profile: Profile::pdfA2b());
        $catalog = new Catalog(1, $document);

        $rendered = $catalog->render();

        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('/S /GTS_PDFA1', $rendered);
        self::assertStringContainsString('/OutputConditionIdentifier (sRGB IEC61966-2.1)', $rendered);
        self::assertStringContainsString('/DestOutputProfile 5 0 R', $rendered);
    }

    #[Test]
    public function it_renders_associated_files_for_pdf_a_3b_attachments(): void
    {
        $document = new Document(profile: Profile::pdfA3b());
        $document->addAttachment('data.xml', '<root/>', 'Machine-readable source', 'application/xml');
        $catalog = new Catalog(1, $document);

        $rendered = $catalog->render();

        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('/Names << /EmbeddedFiles << /Names [(data.xml) 5 0 R] >> >>', $rendered);
        self::assertStringContainsString('/AF [5 0 R]', $rendered);
    }

    #[Test]
    public function it_renders_a_pdf_a_output_intent_for_pdf_a_4(): void
    {
        $document = new Document(profile: Profile::pdfA4());
        $catalog = new Catalog(1, $document);

        $rendered = $catalog->render();

        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent', $rendered);
        self::assertStringContainsString('/S /GTS_PDFA1', $rendered);
        self::assertStringContainsString('/OutputConditionIdentifier (sRGB IEC61966-2.1)', $rendered);
        self::assertStringContainsString('/DestOutputProfile 5 0 R', $rendered);
    }

    #[Test]
    public function it_renders_associated_files_for_pdf_a_4f_attachments(): void
    {
        $document = new Document(profile: Profile::pdfA4f());
        $document->addAttachment('data.xml', '<root/>', 'Machine-readable source', 'application/xml');
        $catalog = new Catalog(1, $document);

        $rendered = $catalog->render();

        self::assertStringContainsString('/Names << /EmbeddedFiles << /Names [(data.xml) 5 0 R] >> >>', $rendered);
        self::assertStringContainsString('/AF [5 0 R]', $rendered);
    }

    #[Test]
    public function it_renders_associated_files_for_pdf_2_0_attachments(): void
    {
        $document = new Document(profile: Profile::pdf20());
        $document->addAttachment(
            'data.json',
            '{"items":[]}',
            'Machine-readable source',
            'application/json',
            AssociatedFileRelationship::DATA,
        );
        $catalog = new Catalog(1, $document);

        $rendered = $catalog->render();

        self::assertStringContainsString('/Names << /EmbeddedFiles << /Names [(data.json) 5 0 R] >> >>', $rendered);
        self::assertStringContainsString('/AF [5 0 R]', $rendered);
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4), language: 'de-DE');
        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');
        $document->registerFont('Helvetica');
        $document->addPage()->addText(
            'Hello',
            new Position(10, 20),
            'Helvetica',
            12,
            new TextOptions(structureTag: StructureTag::Paragraph),
        );
        $catalog = new Catalog(1, $document);

        $rendered = $catalog->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                1,
            ),
        );

        self::assertStringStartsWith("1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata ", $rendered);
        self::assertStringNotContainsString('(de-DE)', $rendered);
        self::assertStringNotContainsString('(demo.txt)', $rendered);
    }
}
