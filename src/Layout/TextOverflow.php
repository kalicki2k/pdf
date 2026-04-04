<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

enum TextOverflow: string
{
    case CLIP = 'clip';
    case ELLIPSIS = 'ellipsis';
}
