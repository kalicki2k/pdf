<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

enum TextAlign: string
{
    case LEFT = 'left';
    case CENTER = 'center';
    case RIGHT = 'right';
    case JUSTIFY = 'justify';
}
