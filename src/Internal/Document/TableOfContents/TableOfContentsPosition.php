<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\TableOfContents;

enum TableOfContentsPosition
{
    case START;
    case END;
    case AFTER_PAGE;
}
