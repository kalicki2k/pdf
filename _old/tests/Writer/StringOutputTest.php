<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Writer;

use Kalle\Pdf\Writer\StringOutput;
use PHPUnit\Framework\TestCase;

final class StringOutputTest extends TestCase
{
    public function testItStartsWithOffsetZero(): void
    {
        $output = new StringOutput();

        self::assertSame(0, $output->offset());
        self::assertSame('', $output->contents());
    }

    public function testItAppendsWrittenBytes(): void
    {
        $output = new StringOutput();

        $output->write('Hello');
        $output->write(' World');

        self::assertSame(11, $output->offset());
        self::assertSame('Hello World', $output->contents());
    }

    public function testItIgnoresEmptyWrites(): void
    {
        $output = new StringOutput();

        $output->write('');

        self::assertSame(0, $output->offset());
        self::assertSame('', $output->contents());
    }
}
