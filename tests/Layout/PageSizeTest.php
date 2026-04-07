<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use InvalidArgumentException;
use Kalle\Pdf\Layout\PageSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageSizeTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: float, 2: float}>
     */
    public static function isoPageSizeProvider(): array
    {
        return [
            'A0' => ['A0', 2383.94, 3370.39],
            'A00' => ['A00', 3370.39, 4767.87],
            'A1' => ['A1', 1683.78, 2383.94],
            'A2' => ['A2', 1190.55, 1683.78],
            'A3' => ['A3', 841.89, 1190.55],
            'A4' => ['A4', 595.28, 841.89],
            'A5' => ['A5', 419.53, 595.28],
            'A6' => ['A6', 297.64, 419.53],
            'A7' => ['A7', 209.76, 297.64],
            'A8' => ['A8', 147.40, 209.76],
            'A9' => ['A9', 104.88, 147.40],
            'B0' => ['B0', 2834.65, 4008.19],
            'B1' => ['B1', 2004.09, 2834.65],
            'B2' => ['B2', 1417.32, 2004.09],
            'B3' => ['B3', 1000.63, 1417.32],
            'B4' => ['B4', 708.66, 1000.63],
            'B5' => ['B5', 498.90, 708.66],
            'B6' => ['B6', 354.33, 498.90],
            'B7' => ['B7', 249.45, 354.33],
            'B8' => ['B8', 175.75, 249.45],
            'B9' => ['B9', 124.72, 175.75],
            'B10' => ['B10', 87.87, 124.72],
            'C0' => ['C0', 2599.37, 3676.54],
            'C1' => ['C1', 1836.85, 2599.37],
            'C2' => ['C2', 1298.27, 1836.85],
            'C3' => ['C3', 918.43, 1298.27],
            'C4' => ['C4', 649.13, 918.43],
            'C5' => ['C5', 459.21, 649.13],
            'C6' => ['C6', 323.15, 459.21],
            'C7' => ['C7', 229.61, 323.15],
            'C8' => ['C8', 161.57, 229.61],
            'C9' => ['C9', 113.39, 161.57],
            'C10' => ['C10', 79.37, 113.39],
        ];
    }

    #[Test]
    #[DataProvider('isoPageSizeProvider')]
    public function it_creates_iso_page_sizes_in_points(string $factory, float $expectedWidth, float $expectedHeight): void
    {
        $pageSize = PageSize::{$factory}();

        self::assertEqualsWithDelta($expectedWidth, $pageSize->width(), 0.01);
        self::assertEqualsWithDelta($expectedHeight, $pageSize->height(), 0.01);
    }

    #[Test]
    public function it_creates_custom_sizes_in_points_and_from_millimeters(): void
    {
        $custom = PageSize::custom(100.0, 200.0);
        $millimeters = PageSize::fromMillimeters(25.4, 50.8);

        self::assertSame(100.0, $custom->width());
        self::assertSame(200.0, $custom->height());
        self::assertEqualsWithDelta(72.0, $millimeters->width(), 0.0001);
        self::assertEqualsWithDelta(144.0, $millimeters->height(), 0.0001);
    }

    #[Test]
    public function it_rejects_non_positive_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page width and height must be greater than zero.');

        PageSize::custom(0.0, 100.0);
    }

    #[Test]
    public function it_can_switch_between_landscape_and_portrait_orientations(): void
    {
        $portrait = PageSize::A4();
        $landscape = $portrait->landscape();

        self::assertSame($landscape, $landscape->landscape());
        self::assertGreaterThan($landscape->height(), $landscape->width());
        self::assertSame($portrait->width(), $landscape->height());
        self::assertSame($portrait->height(), $landscape->width());

        $restoredPortrait = $landscape->portrait();

        self::assertSame($portrait->width(), $restoredPortrait->width());
        self::assertSame($portrait->height(), $restoredPortrait->height());
        self::assertSame($portrait, $portrait->portrait());
    }
}
