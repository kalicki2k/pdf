<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PdfA4Feature;
use Kalle\Pdf\Document\PdfA4ScopePolicy;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class PdfA4ScopePolicyTest extends TestCase
{
    public function testItRejectsPdfA4(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4 is blocked until the dedicated PDF/A-4 policy matrix and PDF 2.0 validation path are implemented.',
        );

        new PdfA4ScopePolicy()->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4()));
    }

    public function testItRejectsPdfA4e(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4e is blocked until PDF/A-4e-specific engineering features and the PDF 2.0 validation path are implemented.',
        );

        new PdfA4ScopePolicy()->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4e()));
    }

    public function testItRejectsPdfA4f(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4f is blocked until the dedicated PDF/A-4f attachment and PDF 2.0 validation path are implemented.',
        );

        new PdfA4ScopePolicy()->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4f()));
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
