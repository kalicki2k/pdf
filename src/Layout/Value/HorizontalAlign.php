<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Value;

enum HorizontalAlign: string
{
    case LEFT = 'left';
    case CENTER = 'center';
    case RIGHT = 'right';
    case JUSTIFY = 'justify';
}
