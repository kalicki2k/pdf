<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

enum TextDirection: string
{
    case LTR = 'ltr';
    case RTL = 'rtl';
}
