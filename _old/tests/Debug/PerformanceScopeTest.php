<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Debug;

use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Debug\LogLevel;
use PHPUnit\Framework\TestCase;

final class PerformanceScopeTest extends TestCase
{
    public function testItReportsStructuredPerformanceMetrics(): void
    {
        $sink = new CollectingDebugSink();
        $debugger = new Debugger(
            DebugConfig::make()->logPerformance(LogLevel::Info),
            $sink,
        );

        $scope = $debugger->startPerformanceScope('document.render', ['page_count' => 1]);
        $scope->stop(['bytes' => 128]);

        self::assertCount(1, $sink->records);
        self::assertSame('performance', $sink->records[0]['channel']);
        self::assertSame('document.render', $sink->records[0]['event']);
        self::assertArrayHasKey('duration_ms', $sink->records[0]['context']);
        self::assertArrayHasKey('memory_delta_kb', $sink->records[0]['context']);
        self::assertArrayHasKey('peak_memory_mb', $sink->records[0]['context']);
        self::assertArrayHasKey('peak_memory_delta_kb', $sink->records[0]['context']);
        self::assertSame(128, $sink->records[0]['context']['bytes']);
    }
}
