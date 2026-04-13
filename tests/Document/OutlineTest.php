<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\Outline;
use Kalle\Pdf\Document\OutlineStyle;
use PHPUnit\Framework\TestCase;

final class OutlineTest extends TestCase
{
    public function testItCreatesAPageOutline(): void
    {
        $outline = Outline::page('Intro', 2);

        self::assertSame('Intro', $outline->title);
        self::assertSame(2, $outline->pageNumber);
        self::assertSame(1, $outline->level);
        self::assertTrue($outline->open);
        self::assertFalse($outline->hasPosition());
    }

    public function testItCreatesAPositionedOutline(): void
    {
        $outline = Outline::position('Section', 3, 72.0, 640.0, 2, false);

        self::assertSame(72.0, $outline->x);
        self::assertSame(640.0, $outline->y);
        self::assertSame(2, $outline->level);
        self::assertFalse($outline->open);
        self::assertTrue($outline->hasPosition());
    }

    public function testItCreatesStyledAlternativeDestinations(): void
    {
        $outline = Outline::fitRectangle('Window', 4, 10, 20, 210, 320)
            ->withStyle(new OutlineStyle()->withColor(Color::hex('#336699'))->withBold()->withItalic()->withAdditionalFlags(4))
            ->asGoToAction();

        self::assertTrue($outline->destination->isFitRectangle());
        self::assertTrue($outline->destination->useGoToAction);
        self::assertSame(7, $outline->style?->pdfFlags());
        self::assertSame([0.2, 0.4, 0.6], $outline->style?->pdfRgbComponents());
    }

    public function testItCreatesNamedAndRemoteDestinations(): void
    {
        $named = Outline::named('Intro', 'intro', 1);
        $remote = $named->withDestination($named->destination->asRemoteGoTo('other.pdf', true));

        self::assertTrue($named->destination->isNamed());
        self::assertSame('intro', $named->destination->namedDestination);
        self::assertTrue($remote->destination->isRemote());
        self::assertSame('other.pdf', $remote->destination->remoteFile);
        self::assertTrue($remote->destination->newWindow);
    }

    public function testItRejectsAnEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Outline title must not be empty.');

        Outline::page('', 1);
    }

    public function testItRejectsAnInvalidPageNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Outline page number must be greater than zero.');

        Outline::page('Broken', 0);
    }

    public function testItRejectsAnInvalidLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Outline level must be greater than zero.');

        Outline::page('Broken', 1, 0);
    }
}
