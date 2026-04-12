<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

enum TaggedStructureTag: string
{
    case ANNOT = 'Annot';
    case ART = 'Art';
    case BIB_ENTRY = 'BibEntry';
    case BLOCK_QUOTE = 'BlockQuote';
    case CAPTION = 'Caption';
    case CODE = 'Code';
    case DIV = 'Div';
    case DOCUMENT = 'Document';
    case EM = 'Em';
    case FIGURE = 'Figure';
    case FORM = 'Form';
    case H1 = 'H1';
    case H2 = 'H2';
    case H3 = 'H3';
    case H4 = 'H4';
    case H5 = 'H5';
    case H6 = 'H6';
    case INDEX = 'Index';
    case L = 'L';
    case LBL = 'Lbl';
    case LINK = 'Link';
    case NON_STRUCT = 'NonStruct';
    case NOTE = 'Note';
    case P = 'P';
    case PART = 'Part';
    case PRIVATE = 'Private';
    case QUOTE = 'Quote';
    case REFERENCE = 'Reference';
    case SECT = 'Sect';
    case SPAN = 'Span';
    case STRONG = 'Strong';
    case TABLE = 'Table';
    case TITLE = 'Title';
    case TOC = 'TOC';
    case TOCI = 'TOCI';
}
