<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Debug;

use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Debug\DebugSink;
use Kalle\Pdf\Debug\LogLevel;
use PHPUnit\Framework\TestCase;

final class DebuggerTest extends TestCase
{
    public function testItLogsOnlyEnabledChannels(): void
    {
        $sink = new CollectingDebugSink();
        $debugger = new Debugger(
            DebugConfig::make()
                ->logLifecycle(LogLevel::Info)
                ->logPerformance(LogLevel::Debug),
            $sink,
        );

        $debugger->lifecycle('document.created', ['page_count' => 1]);
        $debugger->pdf('object.created', ['object_id' => 1]);
        $debugger->performance('document.render', ['duration_ms' => 1.25]);

        self::assertCount(2, $sink->records);
        self::assertSame('lifecycle', $sink->records[0]['channel']);
        self::assertSame('document.created', $sink->records[0]['event']);
        self::assertSame('performance', $sink->records[1]['channel']);
        self::assertSame('document.render', $sink->records[1]['event']);
    }
}

/**
 * @internal
 */
final class CollectingDebugSink implements DebugSink
{
    /** @var list<array{channel: string, level: LogLevel, event: string, context: array<string, scalar|null>}> */
    public array $records = [];

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
}
