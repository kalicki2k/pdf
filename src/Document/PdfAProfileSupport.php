<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function sprintf;

final readonly class PdfAProfileSupport
{
    /**
     * @param array<string, PdfACapabilityRule> $capabilityRules
     */
    public function __construct(
        public string $profileName,
        public bool $isSupported,
        public string $supportSummary,
        private array $capabilityRules,
    ) {
    }

    public static function for(Profile $profile): ?self
    {
        if (!$profile->isPdfA()) {
            return null;
        }

        return match ($profile->name()) {
            'PDF/A-1a' => new self(
                'PDF/A-1a',
                true,
                'Supported for the current PDF/A-1a scope with tagged structure, embedded fonts, XMP/Info metadata, OutputIntent and the guarded annotation/form subset.',
                self::baseCapabilityRules(
                    taggedPdf: true,
                    documentLanguage: true,
                    extractableUnicodeFonts: true,
                    outputIntent: true,
                    infoDictionary: true,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: false,
                    documentEmbeddedAttachments: false,
                    transparency: false,
                ),
            ),
            'PDF/A-1b' => new self(
                'PDF/A-1b',
                true,
                'Supported for the current PDF/A-1b scope with embedded fonts, XMP/Info metadata, OutputIntent and the guarded non-transparent rendering path.',
                self::baseCapabilityRules(
                    taggedPdf: false,
                    documentLanguage: false,
                    extractableUnicodeFonts: false,
                    outputIntent: true,
                    infoDictionary: true,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: false,
                    acroFormFields: false,
                    documentAssociatedFiles: false,
                    documentEmbeddedAttachments: false,
                    transparency: false,
                ),
            ),
            'PDF/A-2a' => new self(
                'PDF/A-2a',
                true,
                'Supported for the current PDF/A-2a scope with tagged structure, embedded Unicode fonts, XMP metadata, OutputIntent, tagged page annotations and the constrained tagged form subset; unsupported annotation types and interactive form variants remain blocked.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: true,
                    documentLanguage: true,
                    extractableUnicodeFonts: true,
                    outputIntent: true,
                    infoDictionary: true,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: false,
                    documentEmbeddedAttachments: false,
                    transparency: true,
                ), [
                    PdfACapability::NON_LINK_PAGE_ANNOTATIONS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Tagged Text, Highlight and FreeText annotations are allowed within the currently validated PDF/A-2a scope; other page annotations remain blocked.',
                    ),
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Tagged text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-2a form scope; push buttons and signature fields remain blocked.',
                    ),
                ]),
            ),
            'PDF/A-2b' => new self(
                'PDF/A-2b',
                true,
                'Supported for the current PDF/A-2b scope with embedded fonts, XMP metadata, OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset and the constrained AcroForm subset.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: false,
                    documentLanguage: false,
                    extractableUnicodeFonts: false,
                    outputIntent: true,
                    infoDictionary: true,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: false,
                    documentEmbeddedAttachments: false,
                    transparency: true,
                ), [
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-2b form scope; push buttons and signature fields remain blocked.',
                    ),
                ]),
            ),
            'PDF/A-2u' => new self(
                'PDF/A-2u',
                true,
                'Supported for the current PDF/A-2u scope with extractable Unicode fonts, XMP metadata, OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset and the constrained AcroForm subset.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: false,
                    documentLanguage: false,
                    extractableUnicodeFonts: true,
                    outputIntent: true,
                    infoDictionary: true,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: false,
                    documentEmbeddedAttachments: false,
                    transparency: true,
                ), [
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-2u form scope; push buttons and signature fields remain blocked.',
                    ),
                ]),
            ),
            'PDF/A-3a' => new self(
                'PDF/A-3a',
                true,
                'Supported for the current PDF/A-3a scope with tagged structure, embedded Unicode fonts, XMP metadata, OutputIntent, tagged page annotations, the constrained tagged form subset and document-level associated files; unsupported annotation types and interactive form variants remain blocked.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: true,
                    documentLanguage: true,
                    extractableUnicodeFonts: true,
                    outputIntent: true,
                    infoDictionary: true,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: true,
                    documentEmbeddedAttachments: true,
                    transparency: true,
                ), [
                    PdfACapability::NON_LINK_PAGE_ANNOTATIONS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Tagged Text, Highlight and FreeText annotations are allowed within the currently validated PDF/A-3a scope; other page annotations remain blocked.',
                    ),
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Tagged text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-3a form scope; push buttons and signature fields remain blocked.',
                    ),
                ]),
            ),
            'PDF/A-3b' => new self(
                'PDF/A-3b',
                true,
                'Supported for the current PDF/A-3b scope with embedded fonts, XMP metadata, OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset, the constrained AcroForm subset and document-level associated files.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: false,
                    documentLanguage: false,
                    extractableUnicodeFonts: false,
                    outputIntent: true,
                    infoDictionary: true,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: true,
                    documentEmbeddedAttachments: true,
                    transparency: true,
                ), [
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-3b form scope; push buttons and signature fields remain blocked.',
                    ),
                ]),
            ),
            'PDF/A-3u' => new self(
                'PDF/A-3u',
                true,
                'Supported for the current PDF/A-3u scope with extractable Unicode fonts, XMP metadata, OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset, the constrained AcroForm subset and document-level associated files.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: false,
                    documentLanguage: false,
                    extractableUnicodeFonts: true,
                    outputIntent: true,
                    infoDictionary: true,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: true,
                    documentEmbeddedAttachments: true,
                    transparency: true,
                ), [
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-3u form scope; push buttons and signature fields remain blocked.',
                    ),
                ]),
            ),
            'PDF/A-4' => new self(
                'PDF/A-4',
                true,
                'Supported for the current base PDF/A-4 scope with PDF 2.0 metadata, pdfaid:rev, no Info dictionary, no OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset and the constrained AcroForm subset; attachments remain blocked.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: false,
                    documentLanguage: false,
                    extractableUnicodeFonts: false,
                    outputIntent: false,
                    infoDictionary: false,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: false,
                    documentEmbeddedAttachments: false,
                    transparency: true,
                ), [
                    PdfACapability::NON_LINK_PAGE_ANNOTATIONS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text, Highlight and FreeText annotations are allowed within the currently validated PDF/A-4 scope; popup-related objects, file-attachment annotations and other page annotations remain blocked.',
                    ),
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-4 form scope; push buttons and signature fields remain blocked.',
                    ),
                ]),
            ),
            'PDF/A-4e' => new self(
                'PDF/A-4e',
                true,
                'Supported for the current constrained PDF/A-4e scope with PDF 2.0 metadata, pdfaid:rev, no Info dictionary, no OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset, the constrained AcroForm subset and the simple optional-content group, membership and visibility-expression subset; RichMedia, 3D and other engineering features remain blocked.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: false,
                    documentLanguage: false,
                    extractableUnicodeFonts: false,
                    outputIntent: false,
                    infoDictionary: false,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: false,
                    documentEmbeddedAttachments: false,
                    transparency: true,
                ), [
                    PdfACapability::NON_LINK_PAGE_ANNOTATIONS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text, Highlight and FreeText annotations are allowed within the currently validated PDF/A-4e scope; popup-related objects, file-attachment annotations and engineering-specific page annotations remain blocked.',
                    ),
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-4e form scope; push buttons, signature fields and engineering-specific interactive features remain blocked.',
                    ),
                    PdfACapability::OPTIONAL_CONTENT_GROUPS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Simple optional content groups, OCMD membership dictionaries, basic /VE expressions and layer visibility via /OCProperties and page resource /Properties are allowed in the current constrained PDF/A-4e scope; RichMedia, 3D and broader engineering features remain blocked.',
                    ),
                ]),
            ),
            'PDF/A-4f' => new self(
                'PDF/A-4f',
                true,
                'Supported for the current PDF/A-4f scope with PDF 2.0 metadata, pdfaid:rev, no Info dictionary, no OutputIntent, the explicit Link/Text/Highlight/FreeText annotation subset, the constrained AcroForm subset and document-level associated-file attachments.',
                self::overrideCapabilityRules(self::baseCapabilityRules(
                    taggedPdf: false,
                    documentLanguage: false,
                    extractableUnicodeFonts: false,
                    outputIntent: false,
                    infoDictionary: false,
                    linkAnnotations: true,
                    nonLinkPageAnnotations: true,
                    acroFormFields: true,
                    documentAssociatedFiles: true,
                    documentEmbeddedAttachments: true,
                    transparency: true,
                ), [
                    PdfACapability::NON_LINK_PAGE_ANNOTATIONS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text, Highlight and FreeText annotations are allowed within the currently validated PDF/A-4f scope; popup-related objects, file-attachment annotations and other page annotations remain blocked.',
                    ),
                    PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                        true,
                        false,
                        'Text fields, checkboxes, radio buttons and choice fields are allowed within the currently validated PDF/A-4f form scope; push buttons and signature fields remain blocked.',
                    ),
                ]),
            ),
            default => new self(
                $profile->name(),
                false,
                'This PDF/A profile is not part of the current capability matrix.',
                [],
            ),
        };
    }

    /**
     * @return array<string, PdfACapabilityRule>
     */
    public function capabilityRules(): array
    {
        return $this->capabilityRules;
    }

    public function capabilityRule(PdfACapability $capability): PdfACapabilityRule
    {
        return $this->capabilityRules[$capability->value]
            ?? new PdfACapabilityRule(false, false, 'This capability is not modeled for the current profile.');
    }

    public function assertSupported(): void
    {
        if ($this->isSupported) {
            return;
        }

        throw new DocumentValidationException(DocumentBuildError::PDFA_PROFILE_NOT_SUPPORTED, sprintf(
            'Profile %s is not supported yet: %s',
            $this->profileName,
            $this->supportSummary,
        ));
    }

    /**
     * @return array<string, PdfACapabilityRule>
     */
    private static function baseCapabilityRules(
        bool $taggedPdf,
        bool $documentLanguage,
        bool $extractableUnicodeFonts,
        bool $outputIntent,
        bool $infoDictionary,
        bool $linkAnnotations,
        bool $nonLinkPageAnnotations,
        bool $acroFormFields,
        bool $documentAssociatedFiles,
        bool $documentEmbeddedAttachments,
        bool $transparency,
    ): array {
        return [
            PdfACapability::TAGGED_PDF->value => new PdfACapabilityRule(
                $taggedPdf,
                $taggedPdf,
                $taggedPdf ? 'Tagged PDF is required and validated for this profile.' : 'Tagged PDF is not required in the current scope.',
            ),
            PdfACapability::DOCUMENT_LANGUAGE->value => new PdfACapabilityRule(
                $documentLanguage,
                $documentLanguage,
                $documentLanguage ? 'A document language is required in the current scope.' : 'A document language is not required in the current scope.',
            ),
            PdfACapability::EMBEDDED_FONTS->value => new PdfACapabilityRule(
                true,
                true,
                'Embedded fonts are required and validated for all currently supported PDF/A profiles.',
            ),
            PdfACapability::EXTRACTABLE_UNICODE_FONTS->value => new PdfACapabilityRule(
                $extractableUnicodeFonts,
                $extractableUnicodeFonts,
                $extractableUnicodeFonts
                    ? 'Extractable embedded Unicode fonts are required in the current scope.'
                    : 'Extractable embedded Unicode fonts are not required in the current scope.',
            ),
            PdfACapability::OUTPUT_INTENT->value => new PdfACapabilityRule(
                $outputIntent,
                $outputIntent,
                $outputIntent
                    ? 'An OutputIntent is required and validated for the current profile.'
                    : 'An OutputIntent is not claimed for the current profile.',
            ),
            PdfACapability::INFO_DICTIONARY->value => new PdfACapabilityRule(
                $infoDictionary,
                $infoDictionary,
                $infoDictionary
                    ? 'The current implementation writes and validates the Info dictionary for this profile.'
                    : 'The current implementation does not claim an Info dictionary path for this profile.',
            ),
            PdfACapability::PDF_A_IDENTIFICATION_METADATA->value => new PdfACapabilityRule(
                true,
                true,
                'PDF/A identification XMP is always written for PDF/A profiles.',
            ),
            PdfACapability::LINK_ANNOTATIONS->value => new PdfACapabilityRule(
                $linkAnnotations,
                false,
                $linkAnnotations
                    ? 'Link annotations are allowed within the currently validated annotation scope.'
                    : 'Link annotations are currently blocked for this profile.',
            ),
            PdfACapability::NON_LINK_PAGE_ANNOTATIONS->value => new PdfACapabilityRule(
                $nonLinkPageAnnotations,
                false,
                $nonLinkPageAnnotations
                    ? 'Non-link page annotations are allowed within the currently validated annotation scope.'
                    : 'Non-link page annotations are blocked in the current scope.',
            ),
            PdfACapability::ACRO_FORM_FIELDS->value => new PdfACapabilityRule(
                $acroFormFields,
                false,
                $acroFormFields
                    ? 'AcroForm fields are allowed within the currently validated form scope.'
                    : 'AcroForm fields are blocked in the current scope.',
            ),
            PdfACapability::DOCUMENT_ASSOCIATED_FILES->value => new PdfACapabilityRule(
                $documentAssociatedFiles,
                false,
                $documentAssociatedFiles
                    ? 'Document-level associated files are allowed in the current scope.'
                    : 'Document-level associated files are blocked in the current scope.',
            ),
            PdfACapability::DOCUMENT_EMBEDDED_ATTACHMENTS->value => new PdfACapabilityRule(
                $documentEmbeddedAttachments,
                false,
                $documentEmbeddedAttachments
                    ? 'Document-level embedded attachments are allowed in the current scope.'
                    : 'Document-level embedded attachments are blocked in the current scope.',
            ),
            PdfACapability::TRANSPARENCY->value => new PdfACapabilityRule(
                $transparency,
                false,
                $transparency
                    ? 'Transparency is allowed only along the currently validated implementation paths.'
                    : 'Transparency is blocked in the current scope.',
            ),
            PdfACapability::OPTIONAL_CONTENT_GROUPS->value => new PdfACapabilityRule(
                false,
                false,
                'Optional content groups remain blocked for all PDF/A profiles in the current implementation.',
            ),
        ];
    }

    /**
     * @param array<string, PdfACapabilityRule> $rules
     * @param array<string, PdfACapabilityRule> $overrides
     * @return array<string, PdfACapabilityRule>
     */
    private static function overrideCapabilityRules(array $rules, array $overrides): array
    {
        foreach ($overrides as $capability => $rule) {
            $rules[$capability] = $rule;
        }

        return $rules;
    }
}
