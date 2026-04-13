<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use RuntimeException;

use function sprintf;
use function str_contains;

final class DocumentBuildException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $profileName,
        public readonly ?string $hint = null,
        ?InvalidArgumentException $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromValidationFailure(Document $document, InvalidArgumentException $previous): self
    {
        $profileName = $document->profile->name();
        $reason = $previous->getMessage();
        $hint = $previous instanceof DocumentValidationException
            ? self::hintForError($document, $previous->error)
            : self::hintFor($document, $reason);
        $message = sprintf(
            "Document build failed for profile %s.\nReason: %s%s",
            $profileName,
            $reason,
            $hint !== null ? "\nHint: " . $hint : '',
        );

        return new self($message, $profileName, $hint, $previous);
    }

    private static function hintForError(Document $document, DocumentBuildError $error): ?string
    {
        return match ($error) {
            DocumentBuildError::PDFA_EMBEDDED_FONTS_REQUIRED => $document->profile->isPdfA()
                ? 'Use embedded fonts via TextOptions(embeddedFont: ...), table text options, or switch to a non-PDF/A profile.'
                : null,
            DocumentBuildError::PDFA_UNICODE_FONTS_REQUIRED => $document->profile->requiresExtractableEmbeddedUnicodeFonts()
                ? 'Use an embedded Unicode-capable font and render the affected text with embeddedFont instead of a standard PDF font.'
                : null,
            DocumentBuildError::DOCUMENT_LANGUAGE_REQUIRED => $document->profile->requiresDocumentLanguage()
                ? "Set ->language('de-DE') or another valid BCP 47 language tag on the document builder."
                : null,
            DocumentBuildError::DOCUMENT_TITLE_REQUIRED => $document->profile->requiresDocumentTitle()
                ? "Set ->title('...') on the document builder before rendering."
                : null,
            DocumentBuildError::IMAGE_ALT_TEXT_REQUIRED => $document->profile->requiresFigureAltText()
                ? 'Provide ImageAccessibility with altText, or mark decorative images as decorative.'
                : null,
            DocumentBuildError::TAGGED_PDF_REQUIRED => $document->profile->requiresTaggedPdf()
                ? 'Use beginStructure()/endStructure() for containers and TextOptions(tag: ...) for leaf roles.'
                : null,
            DocumentBuildError::PDFA_TRANSPARENCY_NOT_ALLOWED => !$document->profile->supportsCurrentTransparencyImplementation()
                ? 'Remove soft masks from image resources or flatten transparency before rendering in this profile.'
                : null,
            DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED => !$document->profile->supportsAcroForms()
                ? 'Remove AcroForm fields for this profile, or switch to a non-PDF/A profile. Only the constrained PDF/A-1a form scope is currently supported.'
                : null,
            DocumentBuildError::PDFA_TAGGED_FORM_SUBSET_REQUIRED => $document->profile->requiresTaggedFormFields()
                ? 'Use only the currently supported tagged form subset for this profile and provide alternative descriptions for each field.'
                : null,
            DocumentBuildError::PDFA_EMBEDDED_ATTACHMENTS_NOT_ALLOWED => !$document->profile->supportsDocumentEmbeddedFileAttachments()
                ? 'Remove embedded attachments for this profile, or switch to a profile that explicitly allows the current attachment path.'
                : null,
            DocumentBuildError::PDFA_ATTACHMENT_MIME_TYPE_REQUIRED => $document->profile->supportsDocumentEmbeddedFileAttachments()
                ? 'Set a MIME type on each EmbeddedFile so the attachment can be serialized as a valid associated file.'
                : null,
            DocumentBuildError::PDFA_ASSOCIATED_FILES_NOT_ALLOWED => !$document->profile->supportsDocumentAssociatedFiles()
                ? 'Use plain attachments only where the profile allows them, or switch to a profile with document-level associated file support.'
                : null,
            DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID => $document->profile->usesPdfAOutputIntent()
                ? 'Use the default PDF/A output intent or pass ->pdfaOutputIntent(...) with a readable ICC profile that matches the document color usage.'
                : null,
            DocumentBuildError::PDFA4_METADATA_INVALID => $document->profile->isPdfA4()
                ? 'Keep PDF/A-4 metadata on the dedicated PDF 2.0 path: write pdfaid:part=4 and pdfaid:rev=2020, omit Info/OutputIntents, and only write pdfaid:conformance for 4e/4f.'
                : null,
            DocumentBuildError::PDFA_LOW_LEVEL_CONTENT_NOT_ALLOWED => $document->profile->isPdfA()
                ? 'Use the high-level document APIs instead of raw PDF dictionary or content stream injections for this profile.'
                : null,
            DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID => $document->profile->isPdfA()
                ? 'Keep the generated PDF/A object graph on the validated serializer path; avoid custom low-level object wiring that bypasses the builder and validator invariants.'
                : null,
            DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID => $document->profile->requiresTaggedPdf()
                ? 'Use beginStructure()/endStructure() consistently and keep the tagged reading order, ParentTree, MCIDs and StructElem hierarchy on the validated tagged PDF path.'
                : null,
            DocumentBuildError::PDFA_METADATA_INCONSISTENT => $document->profile->isPdfA()
                ? 'Keep Info and XMP metadata synchronized for this profile; update title, author, subject, language and timestamps through the document builder instead of low-level metadata overrides.'
                : null,
            DocumentBuildError::PDFA_ACTION_NOT_ALLOWED => $document->profile->isPdfA()
                ? 'Use only PDF/A-safe navigation: internal destinations instead of remote or URI actions, and avoid action dictionaries that the active profile forbids.'
                : null,
        };
    }

    private static function hintFor(Document $document, string $reason): ?string
    {
        if ($document->profile->isPdfA() && self::containsAny($reason, [
            'non-embedded font resource',
            'requires embedded fonts.',
        ])) {
            return 'Use embedded fonts via TextOptions(embeddedFont: ...), table text options, or switch to a non-PDF/A profile.';
        }

        if (
            $document->profile->requiresExtractableEmbeddedUnicodeFonts()
            && self::containsAny($reason, [
                'extractable Unicode fonts',
                'requires embedded Unicode fonts.',
            ])
        ) {
            return 'Use an embedded Unicode-capable font and render the affected text with embeddedFont instead of a standard PDF font.';
        }

        if (
            $document->profile->requiresDocumentLanguage()
            && str_contains($reason, 'requires a document language')
        ) {
            return "Set ->language('de-DE') or another valid BCP 47 language tag on the document builder.";
        }

        if (
            $document->profile->requiresDocumentTitle()
            && str_contains($reason, 'requires a document title')
        ) {
            return "Set ->title('...') on the document builder before rendering.";
        }

        if (
            $document->profile->requiresFigureAltText()
            && str_contains($reason, 'alternative text for image')
        ) {
            return 'Provide ImageAccessibility with altText, or mark decorative images as decorative.';
        }

        if (
            $document->profile->requiresTaggedPdf()
            && self::containsAny($reason, [
                'StructTreeRoot',
                'tagged',
                'requires structured content',
                'requires structured marked content',
            ])
        ) {
            return 'Use beginStructure()/endStructure() for containers and TextOptions(tag: ...) for leaf roles.';
        }

        if (
            !$document->profile->supportsCurrentTransparencyImplementation()
            && str_contains($reason, 'does not allow soft-mask image transparency')
        ) {
            return 'Remove soft masks from image resources or flatten transparency before rendering in this profile.';
        }

        if (
            !$document->profile->supportsAcroForms()
            && self::containsAny($reason, [
                'does not allow AcroForm fields',
                'does not allow text fields',
                'does not allow checkboxes',
                'does not allow radio buttons',
                'does not allow combo boxes',
                'does not allow list boxes',
                'does not allow push buttons',
                'does not allow signature fields',
            ])
        ) {
            return 'Remove AcroForm fields for this profile, or switch to a non-PDF/A profile. Only the constrained PDF/A-1a form scope is currently supported.';
        }

        if (
            $document->profile->requiresTaggedFormFields()
            && self::containsAny($reason, [
                'only allows text and choice fields in the PDF/A-1a form policy',
                'requires tagged form fields in the current implementation',
            ])
        ) {
            return 'Use only the currently supported tagged form subset for this profile and provide alternative descriptions for each field.';
        }

        if (
            !$document->profile->supportsDocumentEmbeddedFileAttachments()
            && self::containsAny($reason, [
                'does not allow embedded file attachments',
                'does not allow page-level file attachment annotations',
            ])
        ) {
            return 'Remove embedded attachments for this profile, or switch to a profile that explicitly allows the current attachment path.';
        }

        if (
            $document->profile->supportsDocumentEmbeddedFileAttachments()
            && str_contains($reason, 'requires an embedded file MIME type')
        ) {
            return 'Set a MIME type on each EmbeddedFile so the attachment can be serialized as a valid associated file.';
        }

        if (
            !$document->profile->supportsDocumentAssociatedFiles()
            && self::containsAny($reason, [
                'does not allow document-level associated files',
                'only allows document-level associated files',
            ])
        ) {
            return 'Use plain attachments only where the profile allows them, or switch to a profile with document-level associated file support.';
        }

        if (
            $document->profile->usesPdfAOutputIntent()
            && self::containsAny($reason, [
                'catalog must serialize an OutputIntents array',
                'PDF/A output intent',
                'ICC profile',
            ])
        ) {
            return 'Use the default PDF/A output intent or pass ->pdfaOutputIntent(...) with a readable ICC profile that matches the document color usage.';
        }

        if (
            $document->profile->isPdfA4()
            && self::containsAny($reason, [
                'must not serialize OutputIntents',
                'must not serialize an Info dictionary',
                'metadata stream must serialize <pdfaid:part>4</pdfaid:part>',
                'metadata stream must serialize <pdfaid:rev>2020</pdfaid:rev>',
                'metadata stream must not serialize a pdfaid:conformance marker',
                'metadata stream must serialize <pdfaid:conformance>',
            ])
        ) {
            return 'Keep PDF/A-4 metadata on the dedicated PDF 2.0 path: write pdfaid:part=4 and pdfaid:rev=2020, omit Info/OutputIntents, and only write pdfaid:conformance for 4e/4f.';
        }

        return null;
    }

    /**
     * @param list<string> $needles
     */
    private static function containsAny(string $haystack, array $needles): bool
    {
        return array_any($needles, fn ($needle) => str_contains($haystack, (string) $needle));
    }
}
