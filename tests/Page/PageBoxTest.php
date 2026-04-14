<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Page\PageBox;
use Kalle\Pdf\Page\PageSize;
use PHPUnit\Framework\TestCase;

final class PageBoxTest extends TestCase
{
    public function testItCarriesBoxCoordinates(): void
    {
        $box = PageBox::fromPoints(20.0, 30.0, 200.0, 300.0);

        self::assertSame(20.0, $box->left);
        self::assertSame(30.0, $box->bottom);
        self::assertSame(200.0, $box->right);
        self::assertSame(300.0, $box->top);
        self::assertSame(180.0, $box->width());
        self::assertSame(270.0, $box->height());
    }

    public function testItRejectsNonPositiveBoxes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page box must have positive width and height.');

        new PageBox(10.0, 10.0, 10.0, 20.0);
    }

    public function testItRejectsBoxesOutsideThePage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page box must lie within the page MediaBox.');

        PageBox::fromPoints(0.0, 0.0, 600.0, 842.0)->assertFitsWithin(PageSize::A4());
    }
}
