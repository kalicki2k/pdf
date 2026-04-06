<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

enum TableOfContentsPosition
{
    case START;
    case END;
    case AFTER_PAGE;
}
