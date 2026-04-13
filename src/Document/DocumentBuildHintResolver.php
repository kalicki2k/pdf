<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final class DocumentBuildHintResolver
{
    public function resolve(Document $document, InvalidArgumentException $exception): ?string
    {
        if (!$exception instanceof DocumentValidationException) {
            return null;
        }

        return $this->hintForError($document, $exception->error);
    }

    private function hintForError(Document $document, DocumentBuildError $error): ?string
    {
        return match ($error) {
            DocumentBuildError::DUPLICATE_NAMED_DESTINATION => 'Use unique names for each named destination so outlines and links resolve unambiguously.',
            DocumentBuildError::DUPLICATE_ATTACHMENT_FILENAME => 'Give each attachment a unique filename before building the document.',
            DocumentBuildError::OUTLINE_REFERENCE_INVALID => 'Point each outline to an existing page or named destination, and keep remote destinations separate from local page references.',
            DocumentBuildError::OUTLINE_HIERARCHY_INVALID => 'Start outlines at level 1 and only increase nesting one level at a time.',
            DocumentBuildError::FORM_FIELD_PAGE_INVALID => 'Attach each form field or radio choice to an existing page in the document.',
            DocumentBuildError::TABLE_OF_CONTENTS_ENTRIES_REQUIRED => 'Add at least one outline or explicit table-of-contents entry before building the table of contents.',
            DocumentBuildError::TABLE_OF_CONTENTS_LAYOUT_INVALID => 'Use page margins and page size that leave positive content width and height for the table of contents.',
            DocumentBuildError::TABLE_OF_CONTENTS_PAGE_COUNT_UNRESOLVED => 'Keep the table-of-contents layout deterministic; avoid configuration that changes page count between estimation and rendering.',
            DocumentBuildError::BUILD_STATE_INVALID => 'This indicates an internal document build-state mismatch; rebuild the serialization plan from the validated builder path instead of reusing partial state.',
            DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID => 'Keep tagged content, form widgets and structure parents on the validated tagged-PDF builder path so structure objects can be allocated consistently.',
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
            DocumentBuildError::PDFA_IMAGE_ACCESSIBILITY_REQUIRED => $document->profile->requiresTaggedImages()
                ? 'Provide ImageAccessibility for each image and either set altText or mark decorative images as decorative.'
                : null,
            DocumentBuildError::TAGGED_PDF_REQUIRED => $document->profile->requiresTaggedPdf()
                ? 'Use beginStructure()/endStructure() for containers and TextOptions(tag: ...) for leaf roles.'
                : null,
            DocumentBuildError::PDFA_ANNOTATION_NOT_ALLOWED => $document->profile->isPdfA()
                ? 'Use only the currently validated page-annotation subset for this profile; unsupported annotation types and tagging combinations remain blocked.'
                : null,
            DocumentBuildError::PDFA_ANNOTATION_APPEARANCE_REQUIRED => $document->profile->requiresAnnotationAppearanceStreams()
                ? 'Provide appearance streams for printable annotations in this profile, or remove the affected annotation.'
                : null,
            DocumentBuildError::PDFA_ANNOTATION_ALT_TEXT_REQUIRED => (
                $document->profile->requiresLinkAnnotationAlternativeDescriptions()
                || $document->profile->requiresPageAnnotationAlternativeDescriptions()
            )
                ? 'Set an accessible label or alternative text on the affected annotation so the tagged PDF path can serialize it accessibly.'
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
            DocumentBuildError::PDFA_FORM_ALT_TEXT_REQUIRED => $document->profile->requiresFormFieldAlternativeDescriptions()
                ? 'Set alternativeName on each affected form field, radio group and radio choice so the tagged form path remains accessible.'
                : null,
            DocumentBuildError::PDFA_PUSH_BUTTON_ACTION_NOT_ALLOWED => $document->profile->isPdfA()
                ? 'Use inert push buttons without URI actions in this profile, or switch to a profile that allows the intended interaction model.'
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
            DocumentBuildError::PDFA_ENCRYPTION_NOT_ALLOWED => $document->profile->isPdfA()
                ? 'Disable document encryption for PDF/A output; archival profiles require an unencrypted file.'
                : null,
            DocumentBuildError::PDFA_IMAGE_COLOR_SPACE_NOT_ALLOWED => $document->profile->isPdfA1()
                ? 'Use the validated PDF/A-1 image path without custom image color space definitions, or move to a profile that supports the intended color handling.'
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
            DocumentBuildError::PDFA_PROFILE_NOT_SUPPORTED => $document->profile->isPdfA()
                ? 'Use only PDF/A profiles that are explicitly enabled in the current implementation scope, or switch to a non-PDF/A profile until the blocked profile family is implemented.'
                : null,
        };
    }

}
