<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TableOfContents;

enum TableOfContentsPosition
{
    case START;
    case END;
    case AFTER_PAGE;
}
