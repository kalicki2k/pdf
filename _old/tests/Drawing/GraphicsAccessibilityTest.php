<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Drawing;

use InvalidArgumentException;
use Kalle\Pdf\Drawing\GraphicsAccessibility;
use PHPUnit\Framework\TestCase;

final class GraphicsAccessibilityTest extends TestCase
{
    public function testItStoresAlternativeText(): void
    {
        $accessibility = GraphicsAccessibility::alternativeText('Trend line for quarterly revenue');

        self::assertSame('Trend line for quarterly revenue', $accessibility->altText);
        self::assertTrue($accessibility->requiresFigureTag());
        self::assertFalse($accessibility->decorative);
    }

    public function testItSupportsDecorativeGraphics(): void
    {
        $accessibility = GraphicsAccessibility::decorative();

        self::assertTrue($accessibility->decorative);
        self::assertFalse($accessibility->requiresFigureTag());
        self::assertNull($accessibility->altText);
    }

    public function testItRejectsEmptyAlternativeText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alternative text must not be empty.');

        GraphicsAccessibility::alternativeText('');
    }
}
