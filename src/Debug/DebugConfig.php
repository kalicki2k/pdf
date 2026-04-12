<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

final readonly class DebugConfig
{
    public function __construct(
        public ?LogLevel $lifecycleLevel = null,
        public ?LogLevel $pdfStructureLevel = null,
        public ?LogLevel $performanceLevel = null,
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    public function logLifecycle(LogLevel $level): self
    {
        return new self(
            lifecycleLevel: $level,
            pdfStructureLevel: $this->pdfStructureLevel,
            performanceLevel: $this->performanceLevel,
        );
    }

    public function logPdfStructure(LogLevel $level): self
    {
        return new self(
            lifecycleLevel: $this->lifecycleLevel,
            pdfStructureLevel: $level,
            performanceLevel: $this->performanceLevel,
        );
    }

    public function logPerformance(LogLevel $level): self
    {
        return new self(
            lifecycleLevel: $this->lifecycleLevel,
            pdfStructureLevel: $this->pdfStructureLevel,
            performanceLevel: $level,
        );
    }

    public function shouldLogLifecycle(): bool
    {
        return $this->lifecycleLevel !== null;
    }

    public function shouldLogPdfStructure(): bool
    {
        return $this->pdfStructureLevel !== null;
    }

    public function shouldLogPerformance(): bool
    {
        return $this->performanceLevel !== null;
    }
}
