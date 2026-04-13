<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\PdfA4Feature;
use Kalle\Pdf\Document\PdfA4ScopePolicy;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class PdfA4ScopePolicyTest extends TestCase
{
    public function testItAllowsBasePdfA4(): void
    {
        self::assertNull((new PdfA4ScopePolicy())->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4())));
    }

    public function testItRejectsPdfA4e(): void
    {
        try {
            new PdfA4ScopePolicy()->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4e()));
            self::fail('Expected DocumentValidationException for blocked PDF/A-4e profile.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::PDFA_PROFILE_NOT_SUPPORTED, $exception->error);
            self::assertSame(
                'Profile PDF/A-4e is blocked until PDF/A-4e-specific engineering features and the PDF 2.0 validation path are implemented.',
                $exception->getMessage(),
            );
        }
    }

    public function testItRejectsPdfA4f(): void
    {
        try {
            new PdfA4ScopePolicy()->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4f()));
            self::fail('Expected DocumentValidationException for blocked PDF/A-4f profile.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::PDFA_PROFILE_NOT_SUPPORTED, $exception->error);
            self::assertSame(
                'Profile PDF/A-4f is blocked until the dedicated PDF/A-4f attachment and PDF 2.0 validation path are implemented.',
                $exception->getMessage(),
            );
        }
    }

    public function testItExposesBasePdfA4FeatureRules(): void
    {
        $policy = new PdfA4ScopePolicy();

        self::assertTrue($policy->featureRule(Profile::pdfA4(), PdfA4Feature::PDF_2_0_BASE)->required);
        self::assertTrue($policy->featureRule(Profile::pdfA4(), PdfA4Feature::REVISION_METADATA)->required);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::OUTPUT_INTENT)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::INFO_DICTIONARY)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::EMBEDDED_ATTACHMENTS)->allowed);
    }

    public function testItExposesPdfA4eSpecificFeatureRules(): void
    {
        $policy = new PdfA4ScopePolicy();

        self::assertTrue($policy->featureRule(Profile::pdfA4e(), PdfA4Feature::ENGINEERING_FEATURES)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4e(), PdfA4Feature::ASSOCIATED_FILES)->allowed);
    }

    public function testItExposesPdfA4fAttachmentFeatureRules(): void
    {
        $policy = new PdfA4ScopePolicy();

        self::assertTrue($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::EMBEDDED_ATTACHMENTS)->allowed);
        self::assertTrue($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::ASSOCIATED_FILES)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::ENGINEERING_FEATURES)->allowed);
    }
}
