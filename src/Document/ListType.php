<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum ListType: string
{
    case BULLET = 'bullet';
    case NUMBERED = 'numbered';
}
