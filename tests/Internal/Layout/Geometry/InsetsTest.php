<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Geometry;

use Kalle\Pdf\Internal\Layout\Geometry\Insets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InsetsTest extends TestCase
{
    #[Test]
    public function it_stores_explicit_insets(): void
    {
        $insets = new Insets(1, 2, 3, 4);

        self::assertSame(1.0, $insets->top);
        self::assertSame(2.0, $insets->right);
        self::assertSame(3.0, $insets->bottom);
        self::assertSame(4.0, $insets->left);
    }

    #[Test]
    public function it_creates_uniform_insets(): void
    {
        $insets = Insets::all(5);

        self::assertSame(5.0, $insets->top);
        self::assertSame(5.0, $insets->right);
        self::assertSame(5.0, $insets->bottom);
        self::assertSame(5.0, $insets->left);
    }

    #[Test]
    public function it_creates_symmetric_insets(): void
    {
        $insets = Insets::symmetric(8, 3);

        self::assertSame(3.0, $insets->top);
        self::assertSame(8.0, $insets->right);
        self::assertSame(3.0, $insets->bottom);
        self::assertSame(8.0, $insets->left);
    }
}
