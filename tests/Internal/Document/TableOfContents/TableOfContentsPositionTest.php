<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\TableOfContents;

use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsPosition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableOfContentsPositionTest extends TestCase
{
    #[Test]
    public function it_exposes_the_expected_positions(): void
    {
        self::assertSame(
            [
                TableOfContentsPosition::START,
                TableOfContentsPosition::END,
                TableOfContentsPosition::AFTER_PAGE,
            ],
            TableOfContentsPosition::cases(),
        );
    }
}
