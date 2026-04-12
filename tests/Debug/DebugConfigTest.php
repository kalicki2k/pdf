<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Debug;

use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Debug\DebugFormat;
use Kalle\Pdf\Debug\JsonDebugSink;
use Kalle\Pdf\Debug\LogLevel;
use Kalle\Pdf\Debug\TextDebugSink;
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

    public function testJsonPresetProvidesDefaultObservabilityLevels(): void
    {
        $config = DebugConfig::json();

        self::assertSame(LogLevel::Info, $config->lifecycleLevel);
        self::assertSame(LogLevel::Trace, $config->pdfStructureLevel);
        self::assertSame(LogLevel::Info, $config->performanceLevel);
        self::assertSame(DebugFormat::Json, $config->format);
        self::assertNull($config->sink);
    }

    public function testJsonPresetCanResolveAFileSinkThroughTheFlowApi(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-debug-');

        if ($path === false) {
            self::fail('Unable to allocate temporary path for debug config test.');
        }

        $config = DebugConfig::json()->toFile($path);

        self::assertInstanceOf(JsonDebugSink::class, $config->sink);
    }

    public function testTextPresetCanResolveAFileSinkThroughTheFlowApi(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-debug-');

        if ($path === false) {
            self::fail('Unable to allocate temporary path for debug config test.');
        }

        $config = DebugConfig::text()->toFile($path);

        self::assertSame(DebugFormat::Text, $config->format);
        self::assertInstanceOf(TextDebugSink::class, $config->sink);
    }
}
