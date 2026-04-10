<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Text\Input;

use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextSegmentTest extends TestCase
{
    #[Test]
    public function it_creates_a_plain_segment(): void
    {
        $segment = TextSegment::plain('Hello');

        self::assertSame('Hello', $segment->text);
        self::assertNull($segment->color);
        self::assertFalse($segment->bold);
        self::assertFalse($segment->italic);
        self::assertFalse($segment->underline);
        self::assertNull($segment->link);
    }

    #[Test]
    public function it_creates_a_colored_segment(): void
    {
        $color = Color::rgb(255, 0, 0);
        $segment = TextSegment::colored('Warning', $color);

        self::assertSame('Warning', $segment->text);
        self::assertSame($color, $segment->color);
        self::assertFalse($segment->bold);
    }

    #[Test]
    public function it_creates_a_link_segment_with_underline_by_default(): void
    {
        $target = LinkTarget::externalUrl('https://example.com');
        $segment = TextSegment::link('Docs', $target);

        self::assertSame('Docs', $segment->text);
        self::assertSame($target, $segment->link);
        self::assertTrue($segment->underline);
    }

    #[Test]
    public function it_can_create_a_link_segment_without_underlining(): void
    {
        $target = LinkTarget::externalUrl('https://example.com');
        $color = Color::gray(0.5);
        $segment = TextSegment::link('Docs', $target, $color, false);

        self::assertSame('Docs', $segment->text);
        self::assertSame($target, $segment->link);
        self::assertSame($color, $segment->color);
        self::assertFalse($segment->underline);
    }

    #[Test]
    public function it_creates_a_bold_segment(): void
    {
        $segment = TextSegment::bold('Important');

        self::assertSame('Important', $segment->text);
        self::assertTrue($segment->bold);
    }

    #[Test]
    public function it_creates_an_italic_segment(): void
    {
        $segment = TextSegment::italic('Note');

        self::assertSame('Note', $segment->text);
        self::assertTrue($segment->italic);
    }

    #[Test]
    public function it_creates_an_underlined_segment(): void
    {
        $segment = TextSegment::underlined('Docs');

        self::assertSame('Docs', $segment->text);
        self::assertTrue($segment->underline);
    }

    #[Test]
    public function it_creates_a_strikethrough_segment(): void
    {
        $segment = TextSegment::strikethrough('Old');

        self::assertSame('Old', $segment->text);
        self::assertTrue($segment->strikethrough);
    }

    #[Test]
    public function it_applies_default_color_and_opacity_without_overwriting_explicit_values(): void
    {
        $defaultColor = Color::gray(0.4);
        $defaultOpacity = Opacity::fill(0.5);
        $explicitColor = Color::rgb(255, 0, 0);
        $explicitOpacity = Opacity::fill(0.25);
        $target = LinkTarget::externalUrl('https://example.com');

        $segmentWithDefaults = (new TextSegment(
            'Hello',
            null,
            null,
            $target,
            bold: true,
            italic: true,
            underline: true,
            strikethrough: true,
        ))->withDefaults($defaultColor, $defaultOpacity);

        $segmentWithExplicitValues = (new TextSegment(
            'World',
            $explicitColor,
            $explicitOpacity,
        ))->withDefaults($defaultColor, $defaultOpacity);

        self::assertSame('0.4 g', $segmentWithDefaults->color?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.5 >>', $segmentWithDefaults->opacity?->renderExtGStateDictionary());
        self::assertSame($target, $segmentWithDefaults->link);
        self::assertTrue($segmentWithDefaults->bold);
        self::assertTrue($segmentWithDefaults->italic);
        self::assertTrue($segmentWithDefaults->underline);
        self::assertTrue($segmentWithDefaults->strikethrough);

        self::assertSame('1 0 0 rg', $segmentWithExplicitValues->color?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.25 >>', $segmentWithExplicitValues->opacity?->renderExtGStateDictionary());
    }
}
