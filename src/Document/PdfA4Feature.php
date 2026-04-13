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
    case ENGINEERING_FEATURES = 'engineering_features';
}
