<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Debug;

use Kalle\Pdf\Debug\LogLevel;
use Kalle\Pdf\Debug\TextDebugSink;
use PHPUnit\Framework\TestCase;

final class TextDebugSinkTest extends TestCase
{
    public function testItWritesReadableTextLinesToAStream(): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to allocate temporary stream for test.');
        }

        $sink = new TextDebugSink($stream);
        $sink->log('performance', LogLevel::Info, 'document.render', [
            'page_count' => 10,
            'compressed' => false,
            'title' => 'Report',
        ]);

        rewind($stream);
        $line = stream_get_contents($stream);

        if ($line === false) {
            self::fail('Unable to read written stream contents.');
        }

        self::assertStringContainsString('level=info', $line);
        self::assertStringContainsString('channel=performance', $line);
        self::assertStringContainsString('event=document.render', $line);
        self::assertStringContainsString('page_count=10', $line);
        self::assertStringContainsString('compressed=false', $line);
        self::assertStringContainsString('title="Report"', $line);
    }
}
