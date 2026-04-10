<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Layout\Position;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PositionTest extends TestCase
{
    #[Test]
    public function it_stores_coordinates(): void
    {
        $position = new Position(10, 20);

        self::assertSame(10.0, $position->x);
        self::assertSame(20.0, $position->y);
    }
}
