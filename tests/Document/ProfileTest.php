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
        self::assertTrue(Profile::pdfA2a()->supportsCurrentPageAnnotationsImplementation());
        self::assertTrue(Profile::pdfA2a()->requiresTaggedPageAnnotations());
        self::assertTrue(Profile::pdfA2a()->requiresTaggedFormFields());
        self::assertTrue(Profile::pdfA2a()->supportsCurrentTextFieldImplementation());
        self::assertFalse(Profile::pdfA2a()->supportsCurrentPushButtonImplementation());
        self::assertFalse(Profile::pdfA3a()->supportsCurrentSignatureFieldImplementation());
        self::assertTrue(Profile::pdfA2u()->supportsCurrentTextFieldImplementation());
        self::assertTrue(Profile::pdfA3b()->supportsCurrentTextFieldImplementation());
        self::assertTrue(Profile::pdfA3u()->supportsCurrentTextFieldImplementation());
        self::assertFalse(Profile::pdfA2u()->supportsCurrentSignatureFieldImplementation());
        self::assertFalse(Profile::pdfA3b()->supportsCurrentSignatureFieldImplementation());
        self::assertTrue(Profile::pdfA2b()->supportsCurrentPageAnnotationsImplementation());
    }

    public function testPdfA4FamilySupportMatrixIsExplicit(): void
    {
        self::assertTrue(Profile::pdfA4()->supportsCurrentPdfAImplementation());
        self::assertTrue(Profile::pdfA4e()->supportsCurrentPdfAImplementation());
        self::assertTrue(Profile::pdfA4f()->supportsCurrentPdfAImplementation());
        self::assertFalse(Profile::pdfA4()->usesPdfAOutputIntent());
        self::assertFalse(Profile::pdfA4()->writesInfoDictionary());
        self::assertTrue(Profile::pdfA4()->writesPdfARevisionMetadata());
        self::assertTrue(Profile::pdfA4e()->writesPdfARevisionMetadata());
        self::assertTrue(Profile::pdfA4f()->writesPdfARevisionMetadata());
        self::assertFalse(Profile::pdfA3b()->writesPdfARevisionMetadata());
        self::assertTrue(Profile::pdfA4()->supportsTransparency());
        self::assertTrue(Profile::pdfA4e()->supportsTransparency());
        self::assertTrue(Profile::pdfA4f()->supportsTransparency());
        self::assertFalse(Profile::pdfA4()->supportsOptionalContentGroups());
        self::assertTrue(Profile::pdfA4e()->supportsOptionalContentGroups());
        self::assertFalse(Profile::pdfA4f()->supportsOptionalContentGroups());
        self::assertFalse(Profile::pdfA4()->supportsCurrentOptionalContentGroupImplementation());
        self::assertTrue(Profile::pdfA4e()->supportsCurrentOptionalContentGroupImplementation());
        self::assertFalse(Profile::pdfA4f()->supportsCurrentOptionalContentGroupImplementation());
        self::assertTrue(Profile::pdfA4()->supportsCurrentPageAnnotationsImplementation());
        self::assertTrue(Profile::pdfA4e()->supportsCurrentPageAnnotationsImplementation());
        self::assertTrue(Profile::pdfA4f()->supportsCurrentPageAnnotationsImplementation());
        self::assertTrue(Profile::pdfA4()->supportsCurrentTextFieldImplementation());
        self::assertTrue(Profile::pdfA4e()->supportsCurrentTextFieldImplementation());
        self::assertTrue(Profile::pdfA4f()->supportsCurrentTextFieldImplementation());
        self::assertFalse(Profile::pdfA4()->supportsCurrentPushButtonImplementation());
        self::assertTrue(Profile::pdfA4e()->supportsCurrentPushButtonImplementation());
        self::assertFalse(Profile::pdfA4f()->supportsCurrentSignatureFieldImplementation());
        self::assertTrue(Profile::pdfA4f()->supportsDocumentAssociatedFiles());
        self::assertTrue(Profile::pdfA4f()->supportsDocumentEmbeddedFileAttachments());

        self::assertSame(
            'Supported for the current base PDF/A-4 scope with PDF 2.0 metadata, pdfaid:rev, no Info dictionary, no OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset and the constrained AcroForm subset; attachments remain blocked.',
            Profile::pdfA4()->pdfaSupport()?->supportSummary,
        );
        self::assertSame(
            'Supported for the current constrained PDF/A-4e scope with PDF 2.0 metadata, pdfaid:rev, no Info dictionary, no OutputIntent, the explicit Link/Text/Highlight/FreeText/RichMedia/3D annotation subset, the constrained AcroForm subset including optional-content state push buttons, and the simple optional-content group, configuration, membership and visibility-expression subset; other engineering features remain blocked.',
            Profile::pdfA4e()->pdfaSupport()?->supportSummary,
        );
        self::assertSame(
            'Supported for the current PDF/A-4f scope with PDF 2.0 metadata, pdfaid:rev, no Info dictionary, no OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset, the constrained AcroForm subset and document-level associated-file attachments.',
            Profile::pdfA4f()->pdfaSupport()?->supportSummary,
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
