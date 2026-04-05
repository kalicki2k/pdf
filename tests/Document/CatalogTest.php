<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Catalog;
use Kalle\Pdf\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase
{
    #[Test]
    public function it_renders_a_minimal_catalog_for_pdf_1_0(): void
    {
        $document = new Document();
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_structure_metadata_for_pdf_1_4(): void
    {
        $document = new Document(version: 1.4, language: 'de-DE');
        $document->registerFont('Helvetica');
        $document->addPage()->addText('Hello', 10, 20, 'Helvetica', 12, 'P');
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n"
            . "<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /Lang (de-DE) /StructTreeRoot 8 0 R >>\n"
            . "endobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_keeps_the_catalog_unstructured_for_pdf_1_4_without_tagged_content(): void
    {
        $document = new Document(version: 1.4, language: 'de-DE');
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_outline_references_when_document_outlines_exist(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $document->addOutline('Intro', $page);
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 7 0 R /PageMode /UseOutlines >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_named_destinations_when_document_destinations_exist(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $document->addDestination('table-demo', $page);
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Dests << /table-demo [4 0 R /Fit] >> >>\nendobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_optional_content_properties_when_document_layers_exist(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $page->layer('Notes', static function (\Kalle\Pdf\Document\Page $page): void {
            $page->addRectangle(10, 20, 30, 40);
        });
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n"
            . "<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [7 0 R] /D << /Order [7 0 R] /ON [7 0 R] >> >> >>\n"
            . "endobj\n",
            $catalog->render(),
        );
    }

    #[Test]
    public function it_renders_embedded_file_name_tree_when_document_attachments_exist(): void
    {
        $document = new Document(version: 1.4);
        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');
        $catalog = new Catalog(1, $document);

        self::assertSame(
            "1 0 obj\n"
            . "<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles << /Names [(demo.txt) 5 0 R] >> >> >>\n"
            . "endobj\n",
            $catalog->render(),
        );
    }
}
