<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

use Psr\Log\LoggerInterface;

final readonly class PsrDebugSink implements DebugSink
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function log(string $channel, LogLevel $level, string $event, array $context = []): void
    {
        $this->logger->log(
            $this->mapLevel($level),
            '[' . $channel . '] ' . $event,
            $context,
        );
    }

    private function mapLevel(LogLevel $level): string
    {
        return match ($level) {
            LogLevel::Error => 'error',
            LogLevel::Warning => 'warning',
            LogLevel::Info => 'info',
            LogLevel::Debug,
            LogLevel::Trace => 'debug',
        };
    }
}
