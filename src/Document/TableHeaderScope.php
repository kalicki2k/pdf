<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum TableHeaderScope: string
{
    case ROW = 'Row';
    case COLUMN = 'Column';
    case BOTH = 'Both';
}
