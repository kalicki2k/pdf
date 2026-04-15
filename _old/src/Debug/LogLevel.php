<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

enum LogLevel: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
    case Debug = 'debug';
    case Trace = 'trace';
}
