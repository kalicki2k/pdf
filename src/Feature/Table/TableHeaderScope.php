<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table;

enum TableHeaderScope: string
{
    case Column = 'Column';
    case Row = 'Row';
    case Both = 'Both';
}
