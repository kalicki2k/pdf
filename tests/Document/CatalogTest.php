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
        $document->addFont('Helvetica');
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
}
