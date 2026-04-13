<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum DocumentBuildError: string
{
    case DUPLICATE_NAMED_DESTINATION = 'duplicate_named_destination';
    case DUPLICATE_ATTACHMENT_FILENAME = 'duplicate_attachment_filename';
    case OUTLINE_REFERENCE_INVALID = 'outline_reference_invalid';
    case OUTLINE_HIERARCHY_INVALID = 'outline_hierarchy_invalid';
    case FORM_FIELD_PAGE_INVALID = 'form_field_page_invalid';
    case TABLE_OF_CONTENTS_ENTRIES_REQUIRED = 'table_of_contents_entries_required';
    case TABLE_OF_CONTENTS_LAYOUT_INVALID = 'table_of_contents_layout_invalid';
    case TABLE_OF_CONTENTS_PAGE_COUNT_UNRESOLVED = 'table_of_contents_page_count_unresolved';
    case TAGGED_STRUCTURE_UNCLOSED = 'tagged_structure_unclosed';
    case TABLE_LAYOUT_INVALID = 'table_layout_invalid';
    case BUILD_STATE_INVALID = 'build_state_invalid';
    case TAGGED_STRUCTURE_BUILD_INVALID = 'tagged_structure_build_invalid';
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
    case PDFA_FORM_ALT_TEXT_REQUIRED = 'pdfa_form_alt_text_required';
    case PDFA_PUSH_BUTTON_ACTION_NOT_ALLOWED = 'pdfa_push_button_action_not_allowed';
    case PDFA_EMBEDDED_ATTACHMENTS_NOT_ALLOWED = 'pdfa_embedded_attachments_not_allowed';
    case PDFA_ATTACHMENT_MIME_TYPE_REQUIRED = 'pdfa_attachment_mime_type_required';
    case PDFA_ASSOCIATED_FILES_NOT_ALLOWED = 'pdfa_associated_files_not_allowed';
    case PDFA_ENCRYPTION_NOT_ALLOWED = 'pdfa_encryption_not_allowed';
    case PDFA_IMAGE_COLOR_SPACE_NOT_ALLOWED = 'pdfa_image_color_space_not_allowed';
    case PDFA_OUTPUT_INTENT_INVALID = 'pdfa_output_intent_invalid';
    case PDFA4_METADATA_INVALID = 'pdfa4_metadata_invalid';
    case PDFA_LOW_LEVEL_CONTENT_NOT_ALLOWED = 'pdfa_low_level_content_not_allowed';
    case PDFA_OBJECT_GRAPH_INVALID = 'pdfa_object_graph_invalid';
    case PDFA_TAGGED_STRUCTURE_INVALID = 'pdfa_tagged_structure_invalid';
    case PDFA_METADATA_INCONSISTENT = 'pdfa_metadata_inconsistent';
    case PDFA_ACTION_NOT_ALLOWED = 'pdfa_action_not_allowed';
    case PDFA_PROFILE_NOT_SUPPORTED = 'pdfa_profile_not_supported';
}
