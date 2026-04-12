<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Outline;
use PHPUnit\Framework\TestCase;

final class OutlineTest extends TestCase
{
    public function testItCreatesAPageOutline(): void
    {
        $outline = Outline::page('Intro', 2);

        self::assertSame('Intro', $outline->title);
        self::assertSame(2, $outline->pageNumber);
        self::assertSame(1, $outline->level);
        self::assertFalse($outline->hasPosition());
    }

    public function testItCreatesAPositionedOutline(): void
    {
        $outline = Outline::position('Section', 3, 72.0, 640.0, 2);

        self::assertSame(72.0, $outline->x);
        self::assertSame(640.0, $outline->y);
        self::assertSame(2, $outline->level);
        self::assertTrue($outline->hasPosition());
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
