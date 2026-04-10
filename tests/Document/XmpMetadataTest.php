<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Model\Document\XmpMetadata;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class XmpMetadataTest extends TestCase
{
    #[Test]
    public function it_renders_xmp_metadata_from_document_properties(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
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
            profile: Profile::standard(1.4),
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
    public function it_writes_xmp_metadata_via_the_output_path(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            title: 'Spec',
            author: 'Kalle',
            subject: 'Testing',
            language: 'de-DE',
        );
        $document->addKeyword('pdf')->addKeyword('tests');
        $metadata = new XmpMetadata(4, $document);
        $output = new StringPdfOutput();

        $metadata->write($output);

        self::assertSame($metadata->render(), $output->contents());
    }

    #[Test]
    public function it_writes_encrypted_xmp_metadata_consistently(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            title: 'Spec',
            author: 'Kalle',
            subject: 'Testing',
            language: 'de-DE',
        );
        $document->addKeyword('pdf')->addKeyword('tests');
        $metadata = new XmpMetadata(4, $document);
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $metadata->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($metadata->render(), 4),
            $output->contents(),
        );
    }

    #[Test]
    public function it_maps_author_creator_and_creator_tool_to_distinct_xmp_fields(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
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

    #[Test]
    public function it_does_not_add_pdf_a_identification_metadata_for_standard_profiles(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            title: 'Spec',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringNotContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $rendered);
        self::assertStringNotContainsString('<pdfaid:part>', $rendered);
        self::assertStringNotContainsString('<pdfaid:conformance>', $rendered);
    }

    #[Test]
    public function it_adds_pdf_ua_identification_metadata_for_pdf_ua_1(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible Copy',
            language: 'de-DE',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('xmlns:pdfuaid="http://www.aiim.org/pdfua/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $rendered);
    }

    #[Test]
    public function it_adds_pdf_a_identification_metadata_for_pdf_a_2u(): void
    {
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'Archive Copy',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $rendered);
    }

    #[Test]
    public function it_adds_pdf_a_identification_metadata_for_pdf_a_2b(): void
    {
        $document = new Document(
            profile: Profile::pdfA2b(),
            title: 'Archive Copy',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>B</pdfaid:conformance>', $rendered);
    }

    #[Test]
    public function it_adds_pdf_a_identification_metadata_for_pdf_a_3b(): void
    {
        $document = new Document(
            profile: Profile::pdfA3b(),
            title: 'Archive Copy',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfaid:part>3</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>B</pdfaid:conformance>', $rendered);
    }

    #[Test]
    public function it_adds_pdf_a_identification_metadata_for_pdf_a_3u(): void
    {
        $document = new Document(
            profile: Profile::pdfA3u(),
            title: 'Archive Copy',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfaid:part>3</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $rendered);
    }

    #[Test]
    public function it_adds_pdf_a_identification_metadata_for_pdf_a_4(): void
    {
        $document = new Document(
            profile: Profile::pdfA4(),
            title: 'Archive Copy',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"', $rendered);
        self::assertStringContainsString('<pdfaid:part>4</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:rev>2020</pdfaid:rev>', $rendered);
        self::assertStringNotContainsString('<pdfaid:conformance>', $rendered);
    }

    #[Test]
    public function it_adds_pdf_a_identification_metadata_for_pdf_a_4e(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Archive Copy',
        );
        $metadata = new XmpMetadata(4, $document);

        $rendered = $metadata->render();

        self::assertStringContainsString('<pdfaid:part>4</pdfaid:part>', $rendered);
        self::assertStringContainsString('<pdfaid:rev>2020</pdfaid:rev>', $rendered);
        self::assertStringContainsString('<pdfaid:conformance>E</pdfaid:conformance>', $rendered);
    }
}
