<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

enum BidiCharacterType: string
{
    case LTR = 'ltr';
    case RTL = 'rtl';
    case EUROPEAN_NUMBER = 'en';
    case ARABIC_NUMBER = 'an';
    case NONSPACING_MARK = 'nsm';
    case SEPARATOR = 'separator';
    case WHITESPACE = 'whitespace';
    case NEUTRAL = 'neutral';
    case CONTROL = 'control';
}
