<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use InvalidArgumentException;
use Kalle\Pdf\Image\ImageAccessibility;
use PHPUnit\Framework\TestCase;

final class ImageAccessibilityTest extends TestCase
{
    public function testItBuildsAlternativeTextAccessibility(): void
    {
        $accessibility = ImageAccessibility::alternativeText('Company logo');

        self::assertSame('Company logo', $accessibility->altText);
        self::assertFalse($accessibility->decorative);
        self::assertTrue($accessibility->requiresFigureTag());
    }

    public function testItBuildsDecorativeAccessibility(): void
    {
        $accessibility = ImageAccessibility::decorative();

        self::assertNull($accessibility->altText);
        self::assertTrue($accessibility->decorative);
        self::assertFalse($accessibility->requiresFigureTag());
    }

    public function testItRejectsEmptyAlternativeText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alternative text must not be empty.');

        ImageAccessibility::alternativeText('');
    }
}
