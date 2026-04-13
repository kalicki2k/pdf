<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

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
        self::assertTrue($support->capabilityRule(PdfACapability::NON_LINK_PAGE_ANNOTATIONS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::ACRO_FORM_FIELDS)->allowed);
    }

    public function testPdfA3aCapabilityMatrixIncludesAssociatedFilesButNotGeneralPageAnnotations(): void
    {
        $support = Profile::pdfA3a()->pdfaSupport();

        self::assertNotNull($support);
        self::assertTrue($support->capabilityRule(PdfACapability::DOCUMENT_ASSOCIATED_FILES)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::DOCUMENT_EMBEDDED_ATTACHMENTS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::NON_LINK_PAGE_ANNOTATIONS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::ACRO_FORM_FIELDS)->allowed);
    }

    public function testPdfA2uAndPdfA3bCapabilityMatrixIncludeCurrentFormScope(): void
    {
        $pdfA2uSupport = Profile::pdfA2u()->pdfaSupport();
        $pdfA3bSupport = Profile::pdfA3b()->pdfaSupport();

        self::assertNotNull($pdfA2uSupport);
        self::assertNotNull($pdfA3bSupport);
        self::assertTrue($pdfA2uSupport->capabilityRule(PdfACapability::ACRO_FORM_FIELDS)->allowed);
        self::assertTrue($pdfA3bSupport->capabilityRule(PdfACapability::ACRO_FORM_FIELDS)->allowed);
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

    public function testPdfA4fCapabilityMatrixReflectsTheCurrentAttachmentAndLinkScope(): void
    {
        $support = Profile::pdfA4f()->pdfaSupport();

        self::assertNotNull($support);
        self::assertTrue($support->isSupported);
        self::assertTrue($support->capabilityRule(PdfACapability::DOCUMENT_ASSOCIATED_FILES)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::DOCUMENT_EMBEDDED_ATTACHMENTS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::LINK_ANNOTATIONS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::NON_LINK_PAGE_ANNOTATIONS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::ACRO_FORM_FIELDS)->allowed);
    }

    public function testPdfA4BaseCapabilityMatrixReflectsTheCurrentSupportedScope(): void
    {
        $support = Profile::pdfA4()->pdfaSupport();

        self::assertNotNull($support);
        self::assertTrue($support->isSupported);
        self::assertTrue($support->capabilityRule(PdfACapability::EMBEDDED_FONTS)->required);
        self::assertTrue($support->capabilityRule(PdfACapability::LINK_ANNOTATIONS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::NON_LINK_PAGE_ANNOTATIONS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::ACRO_FORM_FIELDS)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::OUTPUT_INTENT)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::INFO_DICTIONARY)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::DOCUMENT_EMBEDDED_ATTACHMENTS)->allowed);
    }

    public function testPdfA4eCapabilityMatrixReflectsTheCurrentConstrainedScope(): void
    {
        $support = Profile::pdfA4e()->pdfaSupport();

        self::assertNotNull($support);
        self::assertTrue($support->isSupported);
        self::assertTrue($support->capabilityRule(PdfACapability::LINK_ANNOTATIONS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::NON_LINK_PAGE_ANNOTATIONS)->allowed);
        self::assertTrue($support->capabilityRule(PdfACapability::ACRO_FORM_FIELDS)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::DOCUMENT_EMBEDDED_ATTACHMENTS)->allowed);
        self::assertFalse($support->capabilityRule(PdfACapability::OUTPUT_INTENT)->allowed);
    }
}
