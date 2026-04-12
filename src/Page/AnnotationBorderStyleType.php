<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

enum AnnotationBorderStyleType: string
{
    case SOLID = 'S';
    case DASHED = 'D';
    case BEVELED = 'B';
    case INSET = 'I';
    case UNDERLINE = 'U';
}
