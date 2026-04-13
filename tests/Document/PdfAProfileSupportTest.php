<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\PdfACapability;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class PdfAProfileSupportTest extends TestCase
{
    public function testPdfA2aCapabilityMatrixIsExplicit(): void
    {
        $support = Profile::pdfA2a()->pdfaSupport();

        self::assertNotNull($support);
        self::assertTrue($support->isSupported);
        self::assertTrue($support->capabilityRule(PdfACapability::TAGGED_PDF)->required);
        self::assertTrue($support->capabilityRule(PdfACapability::LINK_ANNOTATIONS)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::NON_LINK_PAGE_ANNOTATIONS)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::ACRO_FORM_FIELDS)->allowed);
    }

    public function testPdfA3aCapabilityMatrixIncludesAssociatedFilesButNotGeneralPageAnnotations(): void
    {
        $support = Profile::pdfA3a()->pdfaSupport();

        self::assertNotNull($support);
        self::assertTrue($support->capabilityRule(PdfACapability::DOCUMENT_ASSOCIATED_FILES)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::DOCUMENT_EMBEDDED_ATTACHMENTS)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::NON_LINK_PAGE_ANNOTATIONS)->allowed);
    }

    public function testPdfA1bCapabilityMatrixShowsVisualReproductionScope(): void
    {
        $support = Profile::pdfA1b()->pdfaSupport();

        self::assertNotNull($support);
        self::assertFalse($support->capabilityRule(PdfACapability::TAGGED_PDF)->required);
        self::assertTrue($support->capabilityRule(PdfACapability::EMBEDDED_FONTS)->required);
        self::assertTrue($support->capabilityRule(PdfACapability::OUTPUT_INTENT)->required);
        self::assertFalse($support->capabilityRule(PdfACapability::TRANSPARENCY)->allowed);
    }

    public function testPdfA4fCapabilityMatrixStaysExplicitlyBlockedDespiteAttachmentPlumbing(): void
    {
        $support = Profile::pdfA4f()->pdfaSupport();

        self::assertNotNull($support);
        self::assertFalse($support->isSupported);
        self::assertTrue($support->capabilityRule(PdfACapability::DOCUMENT_ASSOCIATED_FILES)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::DOCUMENT_EMBEDDED_ATTACHMENTS)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::LINK_ANNOTATIONS)->allowed);
    }

    public function testUnsupportedPdfAProfilesRaiseACodedValidationError(): void
    {
        $support = Profile::pdfA4()->pdfaSupport();

        self::assertNotNull($support);

        try {
            $support->assertSupported();
            self::fail('Expected DocumentValidationException for unsupported PDF/A profile.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::PDFA_PROFILE_NOT_SUPPORTED, $exception->error);
            self::assertSame(
                'Profile PDF/A-4 is not supported yet: PDF/A-4 is blocked behind a dedicated PDF/A-4 policy and PDF 2.0 validation path.',
                $exception->getMessage(),
            );
        }
    }
}
