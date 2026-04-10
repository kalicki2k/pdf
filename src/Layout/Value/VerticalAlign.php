<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Value;

enum VerticalAlign: string
{
    case TOP = 'top';
    case MIDDLE = 'middle';
    case BOTTOM = 'bottom';
}
