<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum PdfACapability: string
{
    case TAGGED_PDF = 'tagged_pdf';
    case DOCUMENT_LANGUAGE = 'document_language';
    case EMBEDDED_FONTS = 'embedded_fonts';
    case EXTRACTABLE_UNICODE_FONTS = 'extractable_unicode_fonts';
    case OUTPUT_INTENT = 'output_intent';
    case INFO_DICTIONARY = 'info_dictionary';
    case PDF_A_IDENTIFICATION_METADATA = 'pdf_a_identification_metadata';
    case LINK_ANNOTATIONS = 'link_annotations';
    case NON_LINK_PAGE_ANNOTATIONS = 'non_link_page_annotations';
    case ACRO_FORM_FIELDS = 'acro_form_fields';
    case DOCUMENT_ASSOCIATED_FILES = 'document_associated_files';
    case DOCUMENT_EMBEDDED_ATTACHMENTS = 'document_embedded_attachments';
    case TRANSPARENCY = 'transparency';
    case OPTIONAL_CONTENT_GROUPS = 'optional_content_groups';
}
