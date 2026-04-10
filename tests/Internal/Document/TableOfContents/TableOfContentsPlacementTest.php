<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\TableOfContents;

use InvalidArgumentException;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableOfContentsPlacementTest extends TestCase
{
    #[Test]
    public function it_resolves_start_and_end_insertion_indices(): void
    {
        self::assertSame(0, TableOfContentsPlacement::start()->insertionIndex(5));
        self::assertSame(5, TableOfContentsPlacement::end()->insertionIndex(5));
    }

    #[Test]
    public function it_resolves_the_insertion_index_after_a_specific_page(): void
    {
        self::assertSame(2, TableOfContentsPlacement::afterPage(2)->insertionIndex(5));
    }

    #[Test]
    public function it_rejects_non_positive_insertion_pages(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents insertion page must be greater than zero.');

        TableOfContentsPlacement::afterPage(0);
    }

    #[Test]
    public function it_rejects_after_page_insertions_out_of_bounds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents insertion page 3 is out of bounds for a document with 2 pages.');

        TableOfContentsPlacement::afterPage(3)->insertionIndex(2);
    }
}
