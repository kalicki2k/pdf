<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

use function sprintf;

final class PdfA4ScopePolicy
{
    public function featureRule(Profile $profile, PdfA4Feature $feature): PdfA4FeatureRule
    {
        if (!$profile->isPdfA4()) {
            throw new InvalidArgumentException(sprintf(
                'PDF/A-4 scope policy only applies to PDF/A-4 profiles. Got %s.',
                $profile->name(),
            ));
        }

        return match ($profile->pdfaConformance()) {
            null => $this->basePdfA4Rule($feature),
            'E' => $this->pdfA4eRule($feature),
            'F' => $this->pdfA4fRule($feature),
            default => new PdfA4FeatureRule(false, false, 'This PDF/A-4 feature combination is not modeled.'),
        };
    }

    public function assertProfileSelectionAllowed(Document $document): void
    {
        if (!$document->profile->isPdfA4()) {
            return;
        }

        if ($document->profile->pdfaConformance() === 'E') {
            throw new InvalidArgumentException(
                'Profile PDF/A-4e is blocked until PDF/A-4e-specific engineering features and the PDF 2.0 validation path are implemented.',
            );
        }

        if ($document->profile->pdfaConformance() === 'F') {
            throw new InvalidArgumentException(
                'Profile PDF/A-4f is blocked until the dedicated PDF/A-4f attachment and PDF 2.0 validation path are implemented.',
            );
        }

        throw new InvalidArgumentException(
            'Profile PDF/A-4 is blocked until the dedicated PDF/A-4 policy matrix and PDF 2.0 validation path are implemented.',
        );
    }

    private function basePdfA4Rule(PdfA4Feature $feature): PdfA4FeatureRule
    {
        return match ($feature) {
            PdfA4Feature::PDF_2_0_BASE => new PdfA4FeatureRule(true, true, 'PDF/A-4 is based on PDF 2.0.'),
            PdfA4Feature::REVISION_METADATA => new PdfA4FeatureRule(true, true, 'PDF/A-4 requires pdfaid:rev metadata.'),
            PdfA4Feature::OUTPUT_INTENT => new PdfA4FeatureRule(false, false, 'The current PDF/A-4 path does not claim an OutputIntent.'),
            PdfA4Feature::INFO_DICTIONARY => new PdfA4FeatureRule(false, false, 'The current PDF/A-4 path does not claim an Info dictionary.'),
            PdfA4Feature::EMBEDDED_ATTACHMENTS => new PdfA4FeatureRule(false, false, 'Base PDF/A-4 does not allow the current embedded attachment path.'),
            PdfA4Feature::ASSOCIATED_FILES => new PdfA4FeatureRule(false, false, 'Base PDF/A-4 does not allow the current associated file path.'),
            PdfA4Feature::ENGINEERING_FEATURES => new PdfA4FeatureRule(false, false, 'Base PDF/A-4 does not include PDF/A-4e engineering features.'),
        };
    }

    private function pdfA4eRule(PdfA4Feature $feature): PdfA4FeatureRule
    {
        return match ($feature) {
            PdfA4Feature::PDF_2_0_BASE => new PdfA4FeatureRule(true, true, 'PDF/A-4e is based on PDF 2.0.'),
            PdfA4Feature::REVISION_METADATA => new PdfA4FeatureRule(true, true, 'PDF/A-4e requires pdfaid:rev metadata.'),
            PdfA4Feature::OUTPUT_INTENT => new PdfA4FeatureRule(false, false, 'The current PDF/A-4e path does not claim an OutputIntent.'),
            PdfA4Feature::INFO_DICTIONARY => new PdfA4FeatureRule(false, false, 'The current PDF/A-4e path does not claim an Info dictionary.'),
            PdfA4Feature::EMBEDDED_ATTACHMENTS => new PdfA4FeatureRule(false, false, 'The current PDF/A-4e path does not allow embedded attachments.'),
            PdfA4Feature::ASSOCIATED_FILES => new PdfA4FeatureRule(false, false, 'The current PDF/A-4e path does not allow associated files.'),
            PdfA4Feature::ENGINEERING_FEATURES => new PdfA4FeatureRule(true, false, 'PDF/A-4e-specific engineering features need dedicated validation before support can be claimed.'),
        };
    }

    private function pdfA4fRule(PdfA4Feature $feature): PdfA4FeatureRule
    {
        return match ($feature) {
            PdfA4Feature::PDF_2_0_BASE => new PdfA4FeatureRule(true, true, 'PDF/A-4f is based on PDF 2.0.'),
            PdfA4Feature::REVISION_METADATA => new PdfA4FeatureRule(true, true, 'PDF/A-4f requires pdfaid:rev metadata.'),
            PdfA4Feature::OUTPUT_INTENT => new PdfA4FeatureRule(false, false, 'The current PDF/A-4f path does not claim an OutputIntent.'),
            PdfA4Feature::INFO_DICTIONARY => new PdfA4FeatureRule(false, false, 'The current PDF/A-4f path does not claim an Info dictionary.'),
            PdfA4Feature::EMBEDDED_ATTACHMENTS => new PdfA4FeatureRule(true, false, 'Attachment plumbing exists for PDF/A-4f, but the full conformance path is still blocked.'),
            PdfA4Feature::ASSOCIATED_FILES => new PdfA4FeatureRule(true, false, 'Associated-file plumbing exists for PDF/A-4f, but the full conformance path is still blocked.'),
            PdfA4Feature::ENGINEERING_FEATURES => new PdfA4FeatureRule(false, false, 'PDF/A-4f does not include PDF/A-4e engineering features.'),
        };
    }
}
