<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    public function testPdfA2bDoesNotRequireExtractableEmbeddedUnicodeFonts(): void
    {
        $profile = Profile::pdfA2b();

        self::assertFalse($profile->requiresExtractableEmbeddedUnicodeFonts());
    }

    public function testPdfA2uRequiresExtractableEmbeddedUnicodeFonts(): void
    {
        $profile = Profile::pdfA2u();

        self::assertTrue($profile->requiresExtractableEmbeddedUnicodeFonts());
    }

    public function testPdfA3uRequiresExtractableEmbeddedUnicodeFontsAndAssociatedFiles(): void
    {
        $profile = Profile::pdfA3u();

        self::assertTrue($profile->requiresExtractableEmbeddedUnicodeFonts());
        self::assertTrue($profile->supportsDocumentEmbeddedFileAttachments());
        self::assertTrue($profile->supportsDocumentAssociatedFiles());
    }

    public function testPdfUaRequiresExtractableEmbeddedUnicodeFonts(): void
    {
        $profile = Profile::pdfUa1();

        self::assertTrue($profile->requiresExtractableEmbeddedUnicodeFonts());
    }

    public function testPdfA1aExposesTheExpectedPolicyMatrix(): void
    {
        $this->assertPdfA1PolicyMatrix(Profile::pdfA1a(), true, true);
        self::assertTrue(Profile::pdfA1a()->supportsCurrentPdfAImplementation());
    }

    public function testPdfA1bExposesTheExpectedPolicyMatrix(): void
    {
        $this->assertPdfA1PolicyMatrix(Profile::pdfA1b(), false, false);
        self::assertTrue(Profile::pdfA1b()->supportsCurrentPdfAImplementation());
    }

    public function testPdfA2AndPdfA3CurrentSupportMatrixIsExplicit(): void
    {
        self::assertTrue(Profile::pdfA2a()->supportsCurrentPdfAImplementation());
        self::assertTrue(Profile::pdfA2b()->supportsCurrentPdfAImplementation());
        self::assertTrue(Profile::pdfA2u()->supportsCurrentPdfAImplementation());
        self::assertTrue(Profile::pdfA3a()->supportsCurrentPdfAImplementation());
        self::assertTrue(Profile::pdfA3b()->supportsCurrentPdfAImplementation());
        self::assertTrue(Profile::pdfA3u()->supportsCurrentPdfAImplementation());
    }

    public function testPdfA4FamilyIsExplicitlyBlockedUntilImplemented(): void
    {
        self::assertFalse(Profile::pdfA4()->supportsCurrentPdfAImplementation());
        self::assertFalse(Profile::pdfA4e()->supportsCurrentPdfAImplementation());
        self::assertFalse(Profile::pdfA4f()->supportsCurrentPdfAImplementation());
        self::assertFalse(Profile::pdfA4()->usesPdfAOutputIntent());
        self::assertFalse(Profile::pdfA4()->writesInfoDictionary());
        self::assertTrue(Profile::pdfA4()->writesPdfARevisionMetadata());
        self::assertTrue(Profile::pdfA4f()->writesPdfARevisionMetadata());
        self::assertFalse(Profile::pdfA3b()->writesPdfARevisionMetadata());
        self::assertFalse(Profile::pdfA4f()->supportsTransparency());
        self::assertTrue(Profile::pdfA4f()->supportsDocumentAssociatedFiles());
        self::assertTrue(Profile::pdfA4f()->supportsDocumentEmbeddedFileAttachments());

        self::assertSame(
            'PDF/A-4 is blocked behind a dedicated PDF/A-4 policy and PDF 2.0 validation path.',
            Profile::pdfA4()->pdfaSupport()?->supportSummary,
        );
    }

    private function assertPdfA1PolicyMatrix(
        Profile $profile,
        bool $requiresTaggedPdf,
        bool $requiresExtractableEmbeddedUnicodeFonts,
    ): void {
        self::assertTrue($profile->isPdfA());
        self::assertTrue($profile->isPdfA1());
        self::assertSame(1, $profile->pdfaPart());
        self::assertTrue($profile->requiresAnnotationAppearanceStreams());
        self::assertSame($requiresTaggedPdf, $profile->requiresTaggedPdf());
        self::assertSame($requiresTaggedPdf, $profile->requiresTaggedImages());
        self::assertSame($requiresTaggedPdf, $profile->requiresFigureAltText());
        self::assertSame($requiresTaggedPdf, $profile->requiresTaggedLinkAnnotations());
        self::assertSame($requiresTaggedPdf, $profile->requiresLinkAnnotationAlternativeDescriptions());
        self::assertSame($requiresTaggedPdf, $profile->requiresTaggedPageAnnotations());
        self::assertSame($requiresTaggedPdf, $profile->requiresPageAnnotationAlternativeDescriptions());
        self::assertSame($requiresTaggedPdf, $profile->requiresTaggedFormFields());
        self::assertSame($requiresTaggedPdf, $profile->requiresFormFieldAlternativeDescriptions());
        self::assertTrue($profile->requiresPrintableAnnotations());
        self::assertSame($requiresExtractableEmbeddedUnicodeFonts, $profile->requiresEmbeddedUnicodeFonts());
        self::assertSame($requiresExtractableEmbeddedUnicodeFonts, $profile->requiresExtractableEmbeddedUnicodeFonts());
        self::assertSame($requiresTaggedPdf, $profile->requiresDocumentLanguage());
        self::assertSame($requiresTaggedPdf, $profile->supportsAcroForms());
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
