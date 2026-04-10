<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Render;

use Kalle\Pdf\Internal\Render\PdfObjectOffsets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfObjectOffsetsTest extends TestCase
{
    #[Test]
    public function it_sorts_entries_and_reports_the_pdf_size(): void
    {
        $offsets = new PdfObjectOffsets([5 => 42, 1 => 7, 3 => 21]);

        self::assertSame([1 => 7, 3 => 21, 5 => 42], $offsets->entries());
        self::assertSame(5, $offsets->highestObjectId());
        self::assertSame(6, $offsets->size());
    }

    #[Test]
    public function it_reports_a_minimal_size_for_empty_offsets(): void
    {
        $offsets = new PdfObjectOffsets([]);

        self::assertSame([], $offsets->entries());
        self::assertSame(0, $offsets->highestObjectId());
        self::assertSame(1, $offsets->size());
    }
}
