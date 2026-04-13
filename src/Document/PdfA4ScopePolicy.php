<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function sprintf;

use InvalidArgumentException;

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

        $blockedReason = $this->blockedSelectionReason($document->profile);

        if ($blockedReason === null) {
            return;
        }

        throw new DocumentValidationException(
            DocumentBuildError::PDFA_PROFILE_NOT_SUPPORTED,
            sprintf(
                'Profile %s is blocked until %s',
                $document->profile->name(),
                $blockedReason,
            ),
        );
    }

    public function blockedSelectionReason(Profile $profile): ?string
    {
        if (!$profile->isPdfA4()) {
            throw new InvalidArgumentException(sprintf(
                'PDF/A-4 scope policy only applies to PDF/A-4 profiles. Got %s.',
                $profile->name(),
            ));
        }

        if ($profile->pdfaConformance() === null || $profile->pdfaConformance() === 'E' || $profile->pdfaConformance() === 'F') {
            return null;
        }

        return 'this PDF/A-4 conformance variant is modeled.';
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
            PdfA4Feature::OPTIONAL_CONTENT => new PdfA4FeatureRule(false, false, 'The current PDF/A-4 base scope does not allow optional content groups or layers.'),
            PdfA4Feature::RICH_MEDIA => new PdfA4FeatureRule(false, false, 'The current PDF/A-4 base scope does not allow RichMedia annotations or assets.'),
            PdfA4Feature::THREE_D_ANNOTATIONS => new PdfA4FeatureRule(false, false, 'The current PDF/A-4 base scope does not allow 3D annotations.'),
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
            PdfA4Feature::OPTIONAL_CONTENT => new PdfA4FeatureRule(false, false, 'Optional-content and engineering-view constructs remain blocked until dedicated PDF/A-4e validation exists.'),
            PdfA4Feature::RICH_MEDIA => new PdfA4FeatureRule(false, false, 'RichMedia-style engineering assets remain blocked until dedicated PDF/A-4e validation exists.'),
            PdfA4Feature::THREE_D_ANNOTATIONS => new PdfA4FeatureRule(false, false, '3D engineering annotations remain blocked until dedicated PDF/A-4e validation exists.'),
            PdfA4Feature::ENGINEERING_FEATURES => new PdfA4FeatureRule(false, false, 'PDF/A-4e-specific engineering features remain blocked until dedicated validation exists.'),
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
            PdfA4Feature::OPTIONAL_CONTENT => new PdfA4FeatureRule(false, false, 'PDF/A-4f does not include the current optional-content or layer path.'),
            PdfA4Feature::RICH_MEDIA => new PdfA4FeatureRule(false, false, 'PDF/A-4f does not include RichMedia engineering assets.'),
            PdfA4Feature::THREE_D_ANNOTATIONS => new PdfA4FeatureRule(false, false, 'PDF/A-4f does not include 3D engineering annotations.'),
            PdfA4Feature::ENGINEERING_FEATURES => new PdfA4FeatureRule(false, false, 'PDF/A-4f does not include PDF/A-4e engineering features.'),
        };
    }
}
