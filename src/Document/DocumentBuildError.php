<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum DocumentBuildError: string
{
    case DOCUMENT_LANGUAGE_REQUIRED = 'document_language_required';
    case DOCUMENT_TITLE_REQUIRED = 'document_title_required';
    case IMAGE_ALT_TEXT_REQUIRED = 'image_alt_text_required';
    case TAGGED_PDF_REQUIRED = 'tagged_pdf_required';
    case PDFA_IMAGE_ACCESSIBILITY_REQUIRED = 'pdfa_image_accessibility_required';
    case PDFA_EMBEDDED_FONTS_REQUIRED = 'pdfa_embedded_fonts_required';
    case PDFA_UNICODE_FONTS_REQUIRED = 'pdfa_unicode_fonts_required';
    case PDFA_ANNOTATION_NOT_ALLOWED = 'pdfa_annotation_not_allowed';
    case PDFA_ANNOTATION_APPEARANCE_REQUIRED = 'pdfa_annotation_appearance_required';
    case PDFA_ANNOTATION_ALT_TEXT_REQUIRED = 'pdfa_annotation_alt_text_required';
    case PDFA_TRANSPARENCY_NOT_ALLOWED = 'pdfa_transparency_not_allowed';
    case PDFA_ACROFORM_NOT_ALLOWED = 'pdfa_acroform_not_allowed';
    case PDFA_TAGGED_FORM_SUBSET_REQUIRED = 'pdfa_tagged_form_subset_required';
    case PDFA_EMBEDDED_ATTACHMENTS_NOT_ALLOWED = 'pdfa_embedded_attachments_not_allowed';
    case PDFA_ATTACHMENT_MIME_TYPE_REQUIRED = 'pdfa_attachment_mime_type_required';
    case PDFA_ASSOCIATED_FILES_NOT_ALLOWED = 'pdfa_associated_files_not_allowed';
    case PDFA_OUTPUT_INTENT_INVALID = 'pdfa_output_intent_invalid';
    case PDFA4_METADATA_INVALID = 'pdfa4_metadata_invalid';
    case PDFA_LOW_LEVEL_CONTENT_NOT_ALLOWED = 'pdfa_low_level_content_not_allowed';
    case PDFA_OBJECT_GRAPH_INVALID = 'pdfa_object_graph_invalid';
    case PDFA_TAGGED_STRUCTURE_INVALID = 'pdfa_tagged_structure_invalid';
    case PDFA_METADATA_INCONSISTENT = 'pdfa_metadata_inconsistent';
    case PDFA_ACTION_NOT_ALLOWED = 'pdfa_action_not_allowed';
    case PDFA_PROFILE_NOT_SUPPORTED = 'pdfa_profile_not_supported';
}
