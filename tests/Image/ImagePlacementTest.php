<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use InvalidArgumentException;
use Kalle\Pdf\Image\ImageAlign;
use Kalle\Pdf\Image\ImagePlacement;
use PHPUnit\Framework\TestCase;

final class ImagePlacementTest extends TestCase
{
    public function testItCreatesFlowPlacementWithAlignmentAndSpacing(): void
    {
        $placement = ImagePlacement::flow(
            width: 120.0,
            align: ImageAlign::RIGHT,
            spacingBefore: 8.0,
            spacingAfter: 12.0,
        );

        self::assertTrue($placement->isFlow());
        self::assertSame(120.0, $placement->width);
        self::assertNull($placement->x);
        self::assertNull($placement->y);
        self::assertSame(ImageAlign::RIGHT, $placement->align);
        self::assertSame(8.0, $placement->spacingBefore);
        self::assertSame(12.0, $placement->spacingAfter);
    }

    public function testItRejectsPartialAbsoluteCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image x and y must be provided together.');

        new ImagePlacement(x: 10.0);
    }

    public function testItRejectsAlignedAbsolutePlacement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Aligned flow images cannot also define absolute x/y coordinates.');

        new ImagePlacement(x: 10.0, y: 20.0, align: ImageAlign::CENTER);
    }
}
