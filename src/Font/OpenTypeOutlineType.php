<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

enum OpenTypeOutlineType: string
{
    case TRUE_TYPE = 'truetype';
    case CFF = 'cff';
}
