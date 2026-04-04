<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

enum TextOverflow: string
{
    case CLIP = 'clip';
    case ELLIPSIS = 'ellipsis';
}
