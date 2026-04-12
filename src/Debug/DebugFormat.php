<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

enum DebugFormat: string
{
    case Json = 'json';
    case Text = 'text';
}
