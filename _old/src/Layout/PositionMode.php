<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

enum PositionMode: string
{
    case ABSOLUTE = 'absolute';
    case RELATIVE = 'relative';
    case STATIC = 'static';
}
