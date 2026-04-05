<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Graphics\Color;
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
}
