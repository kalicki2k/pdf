<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

enum TextScript: string
{
    case LATIN = 'latin';
    case ARABIC = 'arabic';
    case HEBREW = 'hebrew';
    case DEVANAGARI = 'devanagari';
    case BENGALI = 'bengali';
    case GUJARATI = 'gujarati';
    case COMMON = 'common';
    case INHERITED = 'inherited';
    case UNKNOWN = 'unknown';
}
