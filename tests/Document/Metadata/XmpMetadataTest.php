<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Metadata;

use DateTimeImmutable;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Metadata\XmpMetadata;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class XmpMetadataTest extends TestCase
{
    public function testItBuildsAXmpMetadataStreamFromDocumentMetadata(): void
    {
        $metadata = new XmpMetadata();
        $serializedAt = new DateTimeImmutable('2026-04-12T13:14:15+00:00');
        $document = new Document(
            profile: Profile::standard(),
            title: 'Spec',
            author: 'Kalle',
            subject: 'Testing',
            keywords: 'pdfa, regression',
            language: 'de-DE',
            creator: 'Invoice Service',
            creatorTool: 'pdf2 test suite',
        );

        $contents = $metadata->objectContents($document, $serializedAt);

        self::assertStringStartsWith('<< /Type /Metadata /Subtype /XML /Length ', $contents);
        self::assertStringContainsString('<dc:format>application/pdf</dc:format>', $contents);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Spec</rdf:li>', $contents);
        self::assertStringContainsString('<rdf:li>Kalle</rdf:li>', $contents);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Testing</rdf:li>', $contents);
        self::assertStringContainsString('<pdf:Keywords>pdfa, regression</pdf:Keywords>', $contents);
        self::assertStringContainsString('<rdf:li>de-DE</rdf:li>', $contents);
        self::assertStringContainsString('<pdf:Producer>pdf2 test suite</pdf:Producer>', $contents);
        self::assertStringContainsString('<xmp:CreatorTool>Invoice Service</xmp:CreatorTool>', $contents);
        self::assertStringContainsString('<xmp:CreateDate>2026-04-12T13:14:15+00:00</xmp:CreateDate>', $contents);
        self::assertStringContainsString('<xmp:ModifyDate>2026-04-12T13:14:15+00:00</xmp:ModifyDate>', $contents);
        self::assertStringContainsString('<xmp:MetadataDate>2026-04-12T13:14:15+00:00</xmp:MetadataDate>', $contents);
        self::assertStringNotContainsString('<xmp:CreatorTool>pdf2 test suite</xmp:CreatorTool>', $contents);
        self::assertStringContainsString("<?xpacket end=\"w\"?>\nendstream", $contents);
    }

    public function testItAddsPdfAIdentificationMetadata(): void
    {
        $contents = new XmpMetadata()->objectContents(new Document(
            profile: Profile::pdfA2u(),
            title: 'Archive Copy',
        ));

        self::assertStringContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $contents);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $contents);
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $contents);
    }

    public function testItAddsPdfA4IdentificationMetadataWithRevisionAndWithoutConformanceForBasePdfA4(): void
    {
        $contents = new XmpMetadata()->objectContents(new Document(
            profile: Profile::pdfA4(),
            title: 'Archive Copy',
        ));

        self::assertStringContainsString('<pdfaid:part>4</pdfaid:part>', $contents);
        self::assertStringContainsString('<pdfaid:rev>2020</pdfaid:rev>', $contents);
        self::assertStringNotContainsString('<pdfaid:conformance>', $contents);
    }

    public function testItAddsPdfA4fIdentificationMetadataWithRevisionAndConformance(): void
    {
        $contents = new XmpMetadata()->objectContents(new Document(
            profile: Profile::pdfA4f(),
            title: 'Archive Copy',
        ));

        self::assertStringContainsString('<pdfaid:part>4</pdfaid:part>', $contents);
        self::assertStringContainsString('<pdfaid:rev>2020</pdfaid:rev>', $contents);
        self::assertStringContainsString('<pdfaid:conformance>F</pdfaid:conformance>', $contents);
    }

    public function testItAddsPdfUaIdentificationMetadata(): void
    {
        $contents = new XmpMetadata()->objectContents(new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible Copy',
            language: 'de-DE',
        ));

        self::assertStringContainsString('xmlns:pdfuaid="http://www.aiim.org/pdfua/ns/id/"', $contents);
        self::assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $contents);
    }
}
