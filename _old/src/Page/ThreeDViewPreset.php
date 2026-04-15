<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

enum ThreeDViewPreset: string
{
    case DEFAULT = 'Default';
    case OVERVIEW = 'Overview';
    case EXPLODED = 'Exploded';
}
