<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Definition;

enum TableHeaderScope: string
{
    case Column = 'Column';
    case Row = 'Row';
    case Both = 'Both';
}
