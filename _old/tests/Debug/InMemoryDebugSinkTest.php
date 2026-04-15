<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Debug;

use Kalle\Pdf\Debug\InMemoryDebugSink;
use Kalle\Pdf\Debug\LogLevel;
use PHPUnit\Framework\TestCase;

final class InMemoryDebugSinkTest extends TestCase
{
    public function testItCollectsStructuredEventsInMemory(): void
    {
        $sink = new InMemoryDebugSink();

        $sink->log('lifecycle', LogLevel::Info, 'document.created', [
            'page_count' => 1,
        ]);

        $records = $sink->records();

        self::assertCount(1, $records);
        self::assertSame('lifecycle', $records[0]['channel']);
        self::assertSame(LogLevel::Info, $records[0]['level']);
        self::assertSame('document.created', $records[0]['event']);
        self::assertSame(['page_count' => 1], $records[0]['context']);
    }

    public function testItCanClearCollectedEvents(): void
    {
        $sink = new InMemoryDebugSink();

        $sink->log('performance', LogLevel::Debug, 'document.render');
        $sink->clear();

        self::assertSame([], $sink->records());
    }
}
