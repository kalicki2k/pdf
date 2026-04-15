<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

final class InMemoryDebugSink implements DebugSink
{
    /** @var list<array{channel: string, level: LogLevel, event: string, context: array<string, scalar|null>}> */
    private array $records = [];

    /**
     * @param array<string, scalar|null> $context
     */
    public function log(string $channel, LogLevel $level, string $event, array $context = []): void
    {
        $this->records[] = [
            'channel' => $channel,
            'level' => $level,
            'event' => $event,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{channel: string, level: LogLevel, event: string, context: array<string, scalar|null>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
