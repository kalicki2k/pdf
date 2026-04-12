<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Drawing;

use InvalidArgumentException;
use Kalle\Pdf\Drawing\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testItBuildsAPathFromExplicitCommands(): void
    {
        $path = Path::builder()
            ->moveTo(10, 20)
            ->lineTo(30, 40)
            ->curveTo(50, 60, 70, 80, 90, 100)
            ->close()
            ->build();

        self::assertSame([
            ['operator' => 'm', 'values' => [10.0, 20.0]],
            ['operator' => 'l', 'values' => [30.0, 40.0]],
            ['operator' => 'c', 'values' => [50.0, 60.0, 70.0, 80.0, 90.0, 100.0]],
            ['operator' => 'h', 'values' => []],
        ], $path->commands());
    }

    public function testRoundedRectangleBuildsClosedBezierPath(): void
    {
        $path = Path::roundedRectangle(10, 20, 100, 50, 8);

        self::assertCount(10, $path->commands());
        self::assertSame('m', $path->commands()[0]['operator']);
        self::assertSame('c', $path->commands()[2]['operator']);
        self::assertSame('h', $path->commands()[9]['operator']);
    }

    public function testItRejectsAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path requires at least one drawing command.');

        Path::builder()->build();
    }
}
