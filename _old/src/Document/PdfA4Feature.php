<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum PdfA4Feature: string
{
    case PDF_2_0_BASE = 'pdf_2_0_base';
    case REVISION_METADATA = 'revision_metadata';
    case OUTPUT_INTENT = 'output_intent';
    case INFO_DICTIONARY = 'info_dictionary';
    case EMBEDDED_ATTACHMENTS = 'embedded_attachments';
    case ASSOCIATED_FILES = 'associated_files';
    case OPTIONAL_CONTENT = 'optional_content';
    case RICH_MEDIA = 'rich_media';
    case THREE_D_ANNOTATIONS = 'three_d_annotations';
    case ENGINEERING_FEATURES = 'engineering_features';
}
