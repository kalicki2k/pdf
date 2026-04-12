<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Debug;

use Kalle\Pdf\Debug\LogLevel;
use Kalle\Pdf\Debug\PsrDebugSink;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class PsrDebugSinkTest extends TestCase
{
    public function testItMapsLevelsAndFormatsTheMessageConsistently(): void
    {
        $logger = new CollectingLogger();
        $sink = new PsrDebugSink($logger);

        $sink->log('lifecycle', LogLevel::Info, 'document.created', ['page_count' => 1]);
        $sink->log('pdf', LogLevel::Trace, 'object.created', ['object_id' => 7]);

        self::assertSame('info', $logger->records[0]['level']);
        self::assertSame('[lifecycle] document.created', $logger->records[0]['message']);
        self::assertSame(['page_count' => 1], $logger->records[0]['context']);
        self::assertSame('debug', $logger->records[1]['level']);
        self::assertSame('[pdf] object.created', $logger->records[1]['message']);
    }
}

/**
 * @internal
 */
final class CollectingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array}> */
    public array $records = [];

    /**
     * @param mixed $level
     * @param mixed[] $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
