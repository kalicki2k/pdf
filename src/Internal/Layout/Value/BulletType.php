<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Value;

enum BulletType: string
{
    case DISC = "\u{2022}";
    case DASH = '-';
    case CIRCLE = "\u{25E6}";
    case SQUARE = "\u{25AA}";
    case ARROW = "\u{2192}";
}
