<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Debug;

use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Debug\LogLevel;
use PHPUnit\Framework\TestCase;

final class DebugConfigTest extends TestCase
{
    public function testItBuildsAnImmutableFluentConfig(): void
    {
        $base = DebugConfig::make();
        $config = $base
            ->logLifecycle(LogLevel::Info)
            ->logPdfStructure(LogLevel::Trace)
            ->logPerformance(LogLevel::Debug);

        self::assertFalse($base->shouldLogLifecycle());
        self::assertFalse($base->shouldLogPdfStructure());
        self::assertFalse($base->shouldLogPerformance());
        self::assertSame(LogLevel::Info, $config->lifecycleLevel);
        self::assertSame(LogLevel::Trace, $config->pdfStructureLevel);
        self::assertSame(LogLevel::Debug, $config->performanceLevel);
        self::assertTrue($config->shouldLogLifecycle());
        self::assertTrue($config->shouldLogPdfStructure());
        self::assertTrue($config->shouldLogPerformance());
    }
}
