<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

interface DebugSink
{
    /**
     * @param array<string, scalar|null> $context
     */
    public function log(string $channel, LogLevel $level, string $event, array $context = []): void;
}
