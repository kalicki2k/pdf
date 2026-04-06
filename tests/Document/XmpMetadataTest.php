<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\XmpMetadata;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class XmpMetadataTest extends TestCase
{
    #[Test]
    public function it_renders_xmp_metadata_from_document_properties(): void
    {
        $document = new Document(
            version: 1.4,
            title: 'Spec',
            author: 'Kalle',
            subject: 'Testing',
            language: 'de-DE',
        );
        $document->addKeyword('pdf')->addKeyword('tests');
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringStartsWith("4 0 obj\n<< /Type /Metadata /Subtype /XML /Length ", $rendered);
        self::assertStringContainsString('<dc:format>application/pdf</dc:format>', $rendered);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Spec</rdf:li>', $rendered);
        self::assertStringContainsString('<rdf:li>Kalle</rdf:li>', $rendered);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Testing</rdf:li>', $rendered);
        self::assertStringContainsString('<pdf:Keywords>pdf, tests</pdf:Keywords>', $rendered);
        self::assertStringContainsString('<rdf:li>pdf</rdf:li>', $rendered);
        self::assertStringContainsString('<rdf:li>tests</rdf:li>', $rendered);
        self::assertStringContainsString('<rdf:li>de-DE</rdf:li>', $rendered);
        self::assertStringContainsString('<pdf:Producer>' . $document->getProducer() . '</pdf:Producer>', $rendered);
        self::assertStringContainsString('<xmp:CreatorTool>' . $document->getCreatorTool() . '</xmp:CreatorTool>', $rendered);
        self::assertMatchesRegularExpression('/<xmp:CreateDate>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}<\/xmp:CreateDate>/', $rendered);
        self::assertMatchesRegularExpression('/<xmp:ModifyDate>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}<\/xmp:ModifyDate>/', $rendered);
        self::assertMatchesRegularExpression('/<xmp:MetadataDate>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}<\/xmp:MetadataDate>/', $rendered);
        self::assertStringContainsString("<?xpacket end=\"w\"?>\nendstream\nendobj\n", $rendered);
    }

    #[Test]
    public function it_allows_custom_producer_and_creator_tool_metadata(): void
    {
        $document = new Document(
            version: 1.4,
            title: 'Spec',
            creatorTool: 'Acme Backoffice',
        );
        $document
            ->setProducer('kalle/pdf 1.0');
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('<pdf:Producer>kalle/pdf 1.0</pdf:Producer>', $rendered);
        self::assertStringContainsString('<xmp:CreatorTool>Acme Backoffice</xmp:CreatorTool>', $rendered);
    }

    #[Test]
    public function it_maps_author_creator_and_creator_tool_to_distinct_xmp_fields(): void
    {
        $document = new Document(
            version: 1.4,
            title: 'Rechnung 2026-0015',
            author: 'DEIN FIRMENNAME',
            creator: 'Rechnungsservice',
            creatorTool: 'Backoffice Export',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('<dc:creator>', $rendered);
        self::assertStringContainsString('<rdf:li>DEIN FIRMENNAME</rdf:li>', $rendered);
        self::assertStringContainsString('<xmp:CreatorTool>Backoffice Export</xmp:CreatorTool>', $rendered);
        self::assertStringNotContainsString('<rdf:li>Rechnungsservice</rdf:li>', $rendered);
    }
}
