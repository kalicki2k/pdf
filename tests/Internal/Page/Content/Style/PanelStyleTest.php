<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Content\Style;

use InvalidArgumentException;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Page\Content\Style\PanelStyle;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PanelStyleTest extends TestCase
{
    #[Test]
    public function it_stores_panel_style_values(): void
    {
        $style = new PanelStyle(
            paddingHorizontal: 12,
            paddingVertical: 6,
            cornerRadius: 8,
            titleSpacing: 4,
            titleSize: 14,
            bodySize: 10,
            titleAlign: HorizontalAlign::CENTER,
            bodyAlign: HorizontalAlign::RIGHT,
            fillColor: Color::gray(0.9),
            titleColor: Color::rgb(255, 0, 0),
            bodyColor: Color::rgb(0, 0, 255),
            borderWidth: 1.5,
            borderColor: Color::gray(0.2),
            opacity: Opacity::both(0.4),
        );

        self::assertSame(12.0, $style->paddingHorizontal);
        self::assertSame(6.0, $style->paddingVertical);
        self::assertSame(8.0, $style->cornerRadius);
        self::assertSame(4.0, $style->titleSpacing);
        self::assertSame(14, $style->titleSize);
        self::assertSame(10, $style->bodySize);
        self::assertSame(HorizontalAlign::CENTER, $style->titleAlign);
        self::assertSame(HorizontalAlign::RIGHT, $style->bodyAlign);
        self::assertSame('0.9 g', $style->fillColor?->renderNonStrokingOperator());
        self::assertSame('1 0 0 rg', $style->titleColor?->renderNonStrokingOperator());
        self::assertSame('0 0 1 rg', $style->bodyColor?->renderNonStrokingOperator());
        self::assertSame(1.5, $style->borderWidth);
        self::assertSame('0.2 G', $style->borderColor?->renderStrokingOperator());
        self::assertSame('<< /ca 0.4 /CA 0.4 >>', $style->opacity?->renderExtGStateDictionary());
    }

    #[Test]
    public function it_rejects_invalid_panel_style_values(): void
    {
        $cases = [
            ['Panel horizontal padding must be zero or greater.', fn (): PanelStyle => new PanelStyle(paddingHorizontal: -1)],
            ['Panel vertical padding must be zero or greater.', fn (): PanelStyle => new PanelStyle(paddingVertical: -1)],
            ['Panel corner radius must be zero or greater.', fn (): PanelStyle => new PanelStyle(cornerRadius: -1)],
            ['Panel title spacing must be zero or greater.', fn (): PanelStyle => new PanelStyle(titleSpacing: -1)],
            ['Panel title size must be greater than zero.', fn (): PanelStyle => new PanelStyle(titleSize: 0)],
            ['Panel body size must be greater than zero.', fn (): PanelStyle => new PanelStyle(bodySize: 0)],
            ['Panel border width must be greater than zero.', fn (): PanelStyle => new PanelStyle(borderWidth: 0)],
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
