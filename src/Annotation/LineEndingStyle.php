<?php

declare(strict_types=1);

namespace Kalle\Pdf\Annotation;

enum LineEndingStyle: string
{
    case NONE = 'None';
    case SQUARE = 'Square';
    case CIRCLE = 'Circle';
    case DIAMOND = 'Diamond';
    case OPEN_ARROW = 'OpenArrow';
    case CLOSED_ARROW = 'ClosedArrow';
    case BUTT = 'Butt';
    case R_OPEN_ARROW = 'ROpenArrow';
    case R_CLOSED_ARROW = 'RClosedArrow';
    case SLASH = 'Slash';
}
