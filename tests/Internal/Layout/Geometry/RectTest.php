<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Geometry;

use Kalle\Pdf\Layout\Geometry\Rect;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RectTest extends TestCase
{
    #[Test]
    public function it_stores_rectangle_values(): void
    {
        $rect = new Rect(10, 20, 30, 40);

        self::assertSame(10.0, $rect->x);
        self::assertSame(20.0, $rect->y);
        self::assertSame(30.0, $rect->width);
        self::assertSame(40.0, $rect->height);
    }
}
