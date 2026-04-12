<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\ListOptions;
use Kalle\Pdf\Document\ListType;
use PHPUnit\Framework\TestCase;

final class ListOptionsTest extends TestCase
{
    public function testItDefaultsToABulletList(): void
    {
        $options = new ListOptions();

        self::assertSame(ListType::BULLET, $options->type);
        self::assertNull($options->marker);
        self::assertSame(1, $options->start);
    }

    public function testItRejectsAnEmptyMarker(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List marker must not be empty.');

        new ListOptions(marker: '');
    }

    public function testItRejectsANonPositiveNumberingStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List numbering start must be greater than or equal to 1.');

        new ListOptions(type: ListType::NUMBERED, start: 0);
    }

    public function testItRejectsANumberedMarkerWithoutPlaceholder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Numbered list marker must contain a "%d" placeholder.');

        new ListOptions(type: ListType::NUMBERED, marker: ')');
    }
}
