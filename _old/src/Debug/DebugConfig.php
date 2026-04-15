<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final readonly class DebugConfig
{
    public function __construct(
        public ?LogLevel $lifecycleLevel = null,
        public ?LogLevel $pdfStructureLevel = null,
        public ?LogLevel $performanceLevel = null,
        public ?DebugFormat $format = null,
        public ?DebugSink $sink = null,
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    public static function json(): self
    {
        return new self(
            lifecycleLevel: LogLevel::Info,
            pdfStructureLevel: LogLevel::Trace,
            performanceLevel: LogLevel::Info,
            format: DebugFormat::Json,
        );
    }

    public static function text(): self
    {
        return new self(
            lifecycleLevel: LogLevel::Info,
            pdfStructureLevel: LogLevel::Trace,
            performanceLevel: LogLevel::Info,
            format: DebugFormat::Text,
        );
    }

    public function logLifecycle(LogLevel $level): self
    {
        return new self(
            lifecycleLevel: $level,
            pdfStructureLevel: $this->pdfStructureLevel,
            performanceLevel: $this->performanceLevel,
            format: $this->format,
            sink: $this->sink,
        );
    }

    public function logPdfStructure(LogLevel $level): self
    {
        return new self(
            lifecycleLevel: $this->lifecycleLevel,
            pdfStructureLevel: $level,
            performanceLevel: $this->performanceLevel,
            format: $this->format,
            sink: $this->sink,
        );
    }

    public function logPerformance(LogLevel $level): self
    {
        return new self(
            lifecycleLevel: $this->lifecycleLevel,
            pdfStructureLevel: $this->pdfStructureLevel,
            performanceLevel: $level,
            format: $this->format,
            sink: $this->sink,
        );
    }

    public function sink(DebugSink $sink): self
    {
        return new self(
            lifecycleLevel: $this->lifecycleLevel,
            pdfStructureLevel: $this->pdfStructureLevel,
            performanceLevel: $this->performanceLevel,
            format: $this->format,
            sink: $sink,
        );
    }

    public function logger(LoggerInterface $logger): self
    {
        return $this->sink(new PsrDebugSink($logger));
    }

    public function toStdout(): self
    {
        return $this->sink($this->buildSinkForTarget('stdout'));
    }

    public function toStderr(): self
    {
        return $this->sink($this->buildSinkForTarget('stderr'));
    }

    public function toFile(string $path): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('Debug output path must not be empty.');
        }

        return $this->sink($this->buildSinkForTarget($path));
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

    private function buildSinkForTarget(string $target): DebugSink
    {
        return match ($this->format) {
            DebugFormat::Json => match ($target) {
                'stdout' => JsonDebugSink::stdout(),
                'stderr' => JsonDebugSink::stderr(),
                default => JsonDebugSink::fromPath($target),
            },
            DebugFormat::Text => match ($target) {
                'stdout' => TextDebugSink::stdout(),
                'stderr' => TextDebugSink::stderr(),
                default => TextDebugSink::fromPath($target),
            },
            null => throw new InvalidArgumentException('A debug format must be selected before choosing an output target.'),
        };
    }
}
