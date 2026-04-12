<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Debug;

use Kalle\Pdf\Debug\JsonDebugSink;
use Kalle\Pdf\Debug\LogLevel;
use PHPUnit\Framework\TestCase;

final class JsonDebugSinkTest extends TestCase
{
    public function testItWritesStructuredJsonLinesToAStream(): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to allocate temporary stream for test.');
        }

        $sink = new JsonDebugSink($stream);
        $sink->log('pdf', LogLevel::Trace, 'object.created', [
            'object_id' => 7,
            'type' => 'Catalog',
        ]);

        rewind($stream);
        $line = stream_get_contents($stream);

        if ($line === false) {
            self::fail('Unable to read written stream contents.');
        }

        /** @var array{timestamp: string, channel: string, level: string, event: string, message: string, context: array<string, scalar|null>} $decoded */
        $decoded = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('pdf', $decoded['channel']);
        self::assertSame('trace', $decoded['level']);
        self::assertSame('object.created', $decoded['event']);
        self::assertSame('[pdf] object.created', $decoded['message']);
        self::assertSame(7, $decoded['context']['object_id']);
        self::assertSame('Catalog', $decoded['context']['type']);
    }
}
