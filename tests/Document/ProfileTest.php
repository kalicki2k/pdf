<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    public function testPdfA1aExposesTheExpectedPolicyMatrix(): void
    {
        $this->assertPdfA1PolicyMatrix(Profile::pdfA1a(), true, true);
    }

    public function testPdfA1bExposesTheExpectedPolicyMatrix(): void
    {
        $this->assertPdfA1PolicyMatrix(Profile::pdfA1b(), false, false);
    }

    private function assertPdfA1PolicyMatrix(
        Profile $profile,
        bool $requiresTaggedPdf,
        bool $requiresExtractableEmbeddedUnicodeFonts,
    ): void
    {
        self::assertTrue($profile->isPdfA());
        self::assertTrue($profile->isPdfA1());
        self::assertSame(1, $profile->pdfaPart());
        self::assertTrue($profile->requiresAnnotationAppearanceStreams());
        self::assertSame($requiresTaggedPdf, $profile->requiresTaggedPdf());
        self::assertTrue($profile->requiresPrintableAnnotations());
        self::assertSame($requiresExtractableEmbeddedUnicodeFonts, $profile->requiresEmbeddedUnicodeFonts());
        self::assertSame($requiresExtractableEmbeddedUnicodeFonts, $profile->requiresExtractableEmbeddedUnicodeFonts());
        self::assertFalse($profile->requiresDocumentLanguage());
        self::assertFalse($profile->supportsAcroForms());
        self::assertFalse($profile->supportsDocumentEmbeddedFileAttachments());
        self::assertFalse($profile->supportsDocumentAssociatedFiles());
        self::assertFalse($profile->supportsEncryption());
        self::assertFalse($profile->supportsCurrentTransparencyImplementation());
        self::assertFalse($profile->supportsTransparency());
        self::assertFalse($profile->supportsCurrentOptionalContentGroupImplementation());
        self::assertTrue($profile->usesPdfAOutputIntent());
        self::assertTrue($profile->writesPdfAIdentificationMetadata());
        self::assertTrue($profile->writesInfoDictionary());
    }
}
