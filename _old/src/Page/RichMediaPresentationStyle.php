<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

enum RichMediaPresentationStyle: string
{
    case EMBEDDED = 'Embedded';
    case WINDOWED = 'Windowed';
}
