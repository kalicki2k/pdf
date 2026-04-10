<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Value;

enum TextOverflow: string
{
    case CLIP = 'clip';
    case ELLIPSIS = 'ellipsis';
}
