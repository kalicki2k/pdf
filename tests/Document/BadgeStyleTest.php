<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Style\BadgeStyle;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BadgeStyleTest extends TestCase
{
    #[Test]
    public function it_stores_badge_style_values(): void
    {
        $style = new BadgeStyle(
            paddingHorizontal: 8,
            paddingVertical: 4,
            cornerRadius: 3,
            fillColor: Color::gray(0.8),
            textColor: Color::rgb(255, 0, 0),
            borderWidth: 1.5,
            borderColor: Color::rgb(0, 0, 255),
            opacity: Opacity::both(0.4),
        );

        self::assertSame(8.0, $style->paddingHorizontal);
        self::assertSame(4.0, $style->paddingVertical);
        self::assertSame(3.0, $style->cornerRadius);
        self::assertSame('0.8 g', $style->fillColor?->renderNonStrokingOperator());
        self::assertSame('1 0 0 rg', $style->textColor?->renderNonStrokingOperator());
        self::assertSame(1.5, $style->borderWidth);
        self::assertSame('0 0 1 RG', $style->borderColor?->renderStrokingOperator());
        self::assertSame('<< /ca 0.4 /CA 0.4 >>', $style->opacity?->renderExtGStateDictionary());
    }

    #[Test]
    public function it_rejects_invalid_badge_style_values(): void
    {
        $cases = [
            ['Badge horizontal padding must be zero or greater.', fn (): BadgeStyle => new BadgeStyle(paddingHorizontal: -1)],
            ['Badge vertical padding must be zero or greater.', fn (): BadgeStyle => new BadgeStyle(paddingVertical: -1)],
            ['Badge corner radius must be zero or greater.', fn (): BadgeStyle => new BadgeStyle(cornerRadius: -1)],
            ['Badge border width must be greater than zero.', fn (): BadgeStyle => new BadgeStyle(borderWidth: 0)],
        ];

        foreach ($cases as [$expectedMessage, $callback]) {
            try {
                $callback();
                self::fail("Expected exception with message: $expectedMessage");
            } catch (InvalidArgumentException $exception) {
                self::assertSame($expectedMessage, $exception->getMessage());
            }
        }
    }
}
