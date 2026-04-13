<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum DocumentBuildError: string
{
    case DOCUMENT_LANGUAGE_REQUIRED = 'document_language_required';
    case DOCUMENT_TITLE_REQUIRED = 'document_title_required';
    case IMAGE_ALT_TEXT_REQUIRED = 'image_alt_text_required';
    case TAGGED_PDF_REQUIRED = 'tagged_pdf_required';
    case PDFA_EMBEDDED_FONTS_REQUIRED = 'pdfa_embedded_fonts_required';
    case PDFA_UNICODE_FONTS_REQUIRED = 'pdfa_unicode_fonts_required';
    case PDFA_TRANSPARENCY_NOT_ALLOWED = 'pdfa_transparency_not_allowed';
    case PDFA_ACROFORM_NOT_ALLOWED = 'pdfa_acroform_not_allowed';
    case PDFA_TAGGED_FORM_SUBSET_REQUIRED = 'pdfa_tagged_form_subset_required';
    case PDFA_EMBEDDED_ATTACHMENTS_NOT_ALLOWED = 'pdfa_embedded_attachments_not_allowed';
    case PDFA_ATTACHMENT_MIME_TYPE_REQUIRED = 'pdfa_attachment_mime_type_required';
    case PDFA_ASSOCIATED_FILES_NOT_ALLOWED = 'pdfa_associated_files_not_allowed';
    case PDFA_OUTPUT_INTENT_INVALID = 'pdfa_output_intent_invalid';
    case PDFA4_METADATA_INVALID = 'pdfa4_metadata_invalid';
}
