<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use InvalidArgumentException;
use Kalle\Pdf\Image\ImageAlign;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Layout\PositionMode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ImagePlacementTest extends TestCase
{
    public function testItCreatesStaticPlacementWithAlignmentAndSpacing(): void
    {
        $placement = ImagePlacement::static(
            width: 120.0,
            align: ImageAlign::RIGHT,
            spacingBefore: 8.0,
            spacingAfter: 12.0,
        );

        self::assertTrue($placement->isStatic());
        self::assertSame(120.0, $placement->width);
        self::assertNull($placement->left);
        self::assertNull($placement->bottom);
        self::assertSame(ImageAlign::RIGHT, $placement->align);
        self::assertSame(8.0, $placement->spacingBefore);
        self::assertSame(12.0, $placement->spacingAfter);
    }

    public function testItCreatesAbsolutePlacement(): void
    {
        $placement = ImagePlacement::absolute(left: 10.0, bottom: 20.0, width: 120.0);

        self::assertTrue($placement->isAbsolute());
        self::assertSame(10.0, $placement->left);
        self::assertSame(20.0, $placement->bottom);
        self::assertSame(120.0, $placement->width);
    }

    public function testItRejectsStaticPlacementWithInsets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Static image placement does not support explicit left/right/top/bottom insets.');

        $this->newPlacement(
            positionMode: PositionMode::STATIC,
            left: 10.0,
            width: 120.0,
        );
    }

    public function testItRejectsAbsolutePlacementWithoutHorizontalAnchor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image placement requires either left or right.');

        ImagePlacement::absolute(bottom: 20.0, width: 120.0);
    }

    public function testItRejectsAbsolutePlacementWithFlowAlignment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Absolute and relative image placement do not support flow alignment.');

        $this->newPlacement(
            positionMode: PositionMode::ABSOLUTE,
            left: 10.0,
            bottom: 20.0,
            width: 120.0,
            align: ImageAlign::CENTER,
        );
    }

    private function newPlacement(
        PositionMode $positionMode = PositionMode::STATIC,
        ?float $left = null,
        ?float $bottom = null,
        ?float $width = null,
        ?float $height = null,
        ?ImageAlign $align = null,
    ): ImagePlacement {
        $reflection = new ReflectionClass(ImagePlacement::class);
        $constructor = $reflection->getConstructor();

        /** @var ImagePlacement $placement */
        $placement = $reflection->newInstanceWithoutConstructor();
        $constructor?->invoke(
            $placement,
            $positionMode,
            $left,
            null,
            null,
            $bottom,
            $width,
            $height,
            $align,
            0.0,
            0.0,
        );

        return $placement;
    }
}
