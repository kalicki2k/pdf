<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Layout\TableOfContentsPosition;
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
