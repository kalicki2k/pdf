<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Layout\Units;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class UnitsTest extends TestCase
{
    private const FLOAT_DELTA = 0.0001;

    #[Test]
    public function it_has_a_private_constructor_to_enforce_static_usage(): void
    {
        $reflection = new ReflectionClass(Units::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());

        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor->setAccessible(true);
        $constructor->invoke($instance);

        self::assertInstanceOf(Units::class, $instance);
    }

    #[Test]
    public function it_converts_millimeters_to_points(): void
    {
        self::assertEqualsWithDelta(56.6929133858, Units::mm(20), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_returns_points_without_conversion(): void
    {
        self::assertSame(12.0, Units::pt(12));
    }

    #[Test]
    public function it_converts_centimeters_to_points(): void
    {
        self::assertEqualsWithDelta(141.7322834646, Units::cm(5), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_converts_inches_to_points(): void
    {
        self::assertSame(72.0, Units::inch(1));
    }
}
