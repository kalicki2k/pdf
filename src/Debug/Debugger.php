<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

final readonly class Debugger
{
    public function __construct(
        public DebugConfig $config = new DebugConfig(),
        private DebugSink $sink = new NullDebugSink(),
    ) {
    }

    public static function disabled(): self
    {
        return new self();
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function lifecycle(string $event, array $context = []): void
    {
        if (!$this->config->shouldLogLifecycle()) {
            return;
        }

        $level = $this->config->lifecycleLevel;

        if ($level === null) {
            return;
        }

        $this->sink->log('lifecycle', $level, $event, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function pdf(string $event, array $context = []): void
    {
        if (!$this->config->shouldLogPdfStructure()) {
            return;
        }

        $level = $this->config->pdfStructureLevel;

        if ($level === null) {
            return;
        }

        $this->sink->log('pdf', $level, $event, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function performance(string $event, array $context = []): void
    {
        if (!$this->config->shouldLogPerformance()) {
            return;
        }

        $level = $this->config->performanceLevel;

        if ($level === null) {
            return;
        }

        $this->sink->log('performance', $level, $event, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function startPerformanceScope(string $event, array $context = []): PerformanceScope
    {
        if (!$this->config->shouldLogPerformance()) {
            return PerformanceScope::disabled();
        }

        return new PerformanceScope($this, $event, $context);
    }
}
