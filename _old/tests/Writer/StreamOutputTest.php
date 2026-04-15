<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Writer;

use InvalidArgumentException;
use Kalle\Pdf\Writer\StreamOutput;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StreamOutputTest extends TestCase
{
    public function testItStartsAtCurrentStreamOffset(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'abc');

        $output = new StreamOutput($stream);

        self::assertSame(3, $output->offset());

        fclose($stream);
    }

    public function testItWritesBytesToStream(): void
    {
        $stream = fopen('php://temp', 'w+b');
        $output = new StreamOutput($stream);

        $output->write('Hello');
        $output->write(' World');

        rewind($stream);

        self::assertSame(11, $output->offset());
        self::assertSame('Hello World', stream_get_contents($stream));

        fclose($stream);
    }

    public function testItRejectsInvalidStreams(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StreamOutput('not-a-stream');
    }

    public function testItThrowsWhenBytesCannotBeWrittenToTheStream(): void
    {
        $stream = fopen('php://temp', 'rb');
        $output = new StreamOutput($stream);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to write PDF bytes to output stream.');

        try {
            $output->write('Hello');
        } finally {
            fclose($stream);
        }
    }
}
