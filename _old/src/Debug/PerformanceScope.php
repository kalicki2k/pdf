<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

final class PerformanceScope
{
    private ?float $startedAt;
    private ?int $startMemory;
    private ?int $startPeakMemory;
    private bool $stopped = false;

    /**
     * @param array<string, scalar|null> $context
     */
    public function __construct(
        private readonly ?Debugger $debugger,
        private readonly string $event,
        private readonly array $context = [],
        bool $enabled = true,
    ) {
        if (!$enabled) {
            $this->startedAt = null;
            $this->startMemory = null;
            $this->startPeakMemory = null;

            return;
        }

        $this->startedAt = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->startPeakMemory = memory_get_peak_usage(true);
    }

    public static function disabled(): self
    {
        return new self(debugger: null, event: '', enabled: false);
    }

    /**
     * @param array<string, scalar|null> $extraContext
     */
    public function stop(array $extraContext = []): void
    {
        if (
            $this->stopped
            || $this->debugger === null
            || $this->startedAt === null
            || $this->startMemory === null
            || $this->startPeakMemory === null
        ) {
            return;
        }

        $this->stopped = true;

        $durationMs = round((microtime(true) - $this->startedAt) * 1000, 3);
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $this->debugger->performance(
            $this->event,
            [
                ...$this->context,
                ...$extraContext,
                'duration_ms' => $durationMs,
                'memory_delta_kb' => round(($currentMemory - $this->startMemory) / 1024, 3),
                'peak_memory_mb' => round($peakMemory / 1024 / 1024, 3),
                'peak_memory_delta_kb' => round(($peakMemory - $this->startPeakMemory) / 1024, 3),
            ],
        );
    }
}
