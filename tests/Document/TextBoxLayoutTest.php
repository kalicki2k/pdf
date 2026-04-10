<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Geometry\Insets;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Layout\Text\Input\TextBoxOptions;
use Kalle\Pdf\Internal\Layout\Text\TextBoxLayout;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextBoxLayoutTest extends TestCase
{
    #[Test]
    public function it_resolves_text_box_content_metrics_and_middle_start_position(): void
    {
        $layout = TextBoxLayout::fromOptions(
            new Rect(10.0, 20.0, 80.0, 30.0),
            10,
            new TextBoxOptions(
                verticalAlign: VerticalAlign::MIDDLE,
                padding: new Insets(2.0, 3.0, 4.0, 5.0),
            ),
            1.2,
        );

        self::assertSame(15.0, $layout->contentX);
        self::assertSame(72.0, $layout->contentWidth);
        self::assertSame(12.0, $layout->lineHeight);
        self::assertSame(2, $layout->maxLines);
        self::assertSame(37.0, $layout->resolveStartY(10, 2));
    }

    #[Test]
    public function it_rejects_negative_text_box_padding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text box padding must not be negative.');

        TextBoxLayout::fromOptions(
            new Rect(10.0, 20.0, 80.0, 30.0),
            10,
            new TextBoxOptions(padding: new Insets(-1.0, 0.0, 0.0, 0.0)),
            1.2,
        );
    }
}
