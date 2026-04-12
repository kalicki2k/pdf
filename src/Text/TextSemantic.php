<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

enum TextSemantic: string
{
    case CONTENT = 'content';
    case ARTIFACT = 'artifact';
}
