<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentMetadataInspector;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class DocumentMetadataInspectorTest extends TestCase
{
    public function testItDetectsInfoMetadataFields(): void
    {
        $document = new Document(
            profile: Profile::standard(),
            keywords: 'archive, pdfa',
        );

        self::assertTrue((new DocumentMetadataInspector())->hasInfoMetadata($document));
    }

    public function testItSkipsMetadataStreamsForProfilesWithoutXmpSupport(): void
    {
        $document = new Document(
            profile: Profile::pdf10(),
            title: 'Example Title',
        );

        self::assertFalse((new DocumentMetadataInspector())->usesMetadataStream($document));
    }

    public function testItUsesDefaultSrgbOutputIntentWhenNoneIsConfigured(): void
    {
        $document = new Document(profile: Profile::pdfA3b());

        $outputIntent = (new DocumentMetadataInspector())->resolvePdfAOutputIntent($document);

        self::assertInstanceOf(PdfAOutputIntent::class, $outputIntent);
        self::assertSame('sRGB IEC61966-2.1', $outputIntent->outputConditionIdentifier);
    }
}
