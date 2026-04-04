<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum VerticalAlign: string
{
    case TOP = 'top';
    case MIDDLE = 'middle';
    case BOTTOM = 'bottom';
}
