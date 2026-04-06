<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Style\CalloutStyle;
use Kalle\Pdf\Document\Style\PanelStyle;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CalloutStyleTest extends TestCase
{
    #[Test]
    public function it_stores_callout_style_values(): void
    {
        $panelStyle = new PanelStyle(cornerRadius: 8);
        $style = new CalloutStyle(
            panelStyle: $panelStyle,
            pointerBaseWidth: 18,
            pointerStrokeWidth: 1.5,
            pointerStrokeColor: Color::rgb(255, 0, 0),
            pointerFillColor: Color::gray(0.9),
            pointerOpacity: Opacity::both(0.4),
        );

        self::assertSame($panelStyle, $style->panelStyle);
        self::assertSame(18.0, $style->pointerBaseWidth);
        self::assertSame(1.5, $style->pointerStrokeWidth);
        self::assertSame('1 0 0 RG', $style->pointerStrokeColor?->renderStrokingOperator());
        self::assertSame('0.9 g', $style->pointerFillColor?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.4 /CA 0.4 >>', $style->pointerOpacity?->renderExtGStateDictionary());
    }

    #[Test]
    public function it_rejects_invalid_callout_style_values(): void
    {
        $cases = [
            ['Callout pointer base width must be greater than zero.', fn (): CalloutStyle => new CalloutStyle(pointerBaseWidth: 0)],
            ['Callout pointer stroke width must be greater than zero.', fn (): CalloutStyle => new CalloutStyle(pointerStrokeWidth: 0)],
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
