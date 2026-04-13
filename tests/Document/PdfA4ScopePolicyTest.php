<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
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

    public function testItAllowsPdfA4e(): void
    {
        self::assertNull((new PdfA4ScopePolicy())->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4e())));
    }

    public function testItAllowsPdfA4f(): void
    {
        self::assertNull((new PdfA4ScopePolicy())->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4f())));
    }

    public function testItExposesBasePdfA4FeatureRules(): void
    {
        $policy = new PdfA4ScopePolicy();

        self::assertTrue($policy->featureRule(Profile::pdfA4(), PdfA4Feature::PDF_2_0_BASE)->required);
        self::assertTrue($policy->featureRule(Profile::pdfA4(), PdfA4Feature::REVISION_METADATA)->required);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::OUTPUT_INTENT)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::INFO_DICTIONARY)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::EMBEDDED_ATTACHMENTS)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::OPTIONAL_CONTENT)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::RICH_MEDIA)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4(), PdfA4Feature::THREE_D_ANNOTATIONS)->allowed);
    }

    public function testItExposesPdfA4eSpecificFeatureRules(): void
    {
        $policy = new PdfA4ScopePolicy();

        self::assertFalse($policy->featureRule(Profile::pdfA4e(), PdfA4Feature::ENGINEERING_FEATURES)->allowed);
        self::assertTrue($policy->featureRule(Profile::pdfA4e(), PdfA4Feature::OPTIONAL_CONTENT)->allowed);
        self::assertTrue($policy->featureRule(Profile::pdfA4e(), PdfA4Feature::RICH_MEDIA)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4e(), PdfA4Feature::THREE_D_ANNOTATIONS)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4e(), PdfA4Feature::ASSOCIATED_FILES)->allowed);
    }

    public function testItExposesPdfA4fAttachmentFeatureRules(): void
    {
        $policy = new PdfA4ScopePolicy();

        self::assertTrue($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::EMBEDDED_ATTACHMENTS)->allowed);
        self::assertTrue($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::ASSOCIATED_FILES)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::OPTIONAL_CONTENT)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::RICH_MEDIA)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::THREE_D_ANNOTATIONS)->allowed);
        self::assertFalse($policy->featureRule(Profile::pdfA4f(), PdfA4Feature::ENGINEERING_FEATURES)->allowed);
    }
}
