<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

enum ArabicJoiningType: string
{
    case DUAL = 'dual';
    case RIGHT = 'right';
    case NON_JOINING = 'non_joining';
    case TRANSPARENT = 'transparent';
}
