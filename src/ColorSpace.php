<?php

declare(strict_types=1);

namespace Kalle\Pdf;

enum ColorSpace: string
{
    case GRAY = 'gray';
    case RGB = 'rgb';
    case CMYK = 'cmyk';
}
