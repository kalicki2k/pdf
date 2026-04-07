<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

enum StructureTag: string
{
    case Document = 'Document';
    case Heading1 = 'H1';
    case Heading2 = 'H2';
    case Heading3 = 'H3';
    case Heading4 = 'H4';
    case Heading5 = 'H5';
    case Heading6 = 'H6';
    case Paragraph = 'P';
    case Span = 'Span';
    case Link = 'Link';
    case List = 'L';
    case ListItem = 'LI';
    case Label = 'Lbl';
    case ListBody = 'LBody';
    case Quote = 'Quote';
    case Note = 'Note';
    case Part = 'Part';
    case Section = 'Sect';
    case Article = 'Art';
    case Division = 'Div';
    case Table = 'Table';
    case TableRow = 'TR';
    case TableHeaderCell = 'TH';
    case TableDataCell = 'TD';
}
