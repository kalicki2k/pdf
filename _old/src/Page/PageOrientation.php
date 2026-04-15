<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

enum PageOrientation: string
{
    case PORTRAIT = 'portrait';
    case LANDSCAPE = 'landscape';
}
