<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Render\CountingPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CountingPdfOutputTest extends TestCase
{
    #[Test]
    public function it_counts_written_bytes_without_buffering_them(): void
    {
        $output = new CountingPdfOutput();

        $output->write('ab');
        $output->write("c\n");

        self::assertSame(4, $output->offset());
    }
}
