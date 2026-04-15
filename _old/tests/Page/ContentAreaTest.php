<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Page\ContentArea;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use PHPUnit\Framework\TestCase;

final class ContentAreaTest extends TestCase
{
    public function testItCalculatesWidthAndHeight(): void
    {
        $area = new ContentArea(
            left: 56.693,
            right: 538.583,
            top: 785.197,
            bottom: 56.693,
        );

        self::assertEqualsWithDelta(481.89, $area->width(), 0.0001);
        self::assertEqualsWithDelta(728.504, $area->height(), 0.0001);
    }

    public function testPageBuildsAContentAreaFromMarginAndSize(): void
    {
        $page = new Page(
            size: PageSize::A4(),
            margin: Margin::all(Units::mm(20)),
        );

        $area = $page->contentArea();

        self::assertEqualsWithDelta(56.693, $area->left, 0.001);
        self::assertEqualsWithDelta(538.583, $area->right, 0.001);
        self::assertEqualsWithDelta(785.197, $area->top, 0.001);
        self::assertEqualsWithDelta(56.693, $area->bottom, 0.001);
        self::assertEqualsWithDelta(481.89, $area->width(), 0.001);
        self::assertEqualsWithDelta(728.504, $area->height(), 0.001);
    }

    public function testPageUsesTheFullPageAreaWhenNoMarginIsSet(): void
    {
        $page = new Page(PageSize::A5());

        $area = $page->contentArea();

        self::assertSame(0.0, $area->left);
        self::assertSame(PageSize::A5()->width(), $area->right);
        self::assertSame(PageSize::A5()->height(), $area->top);
        self::assertSame(0.0, $area->bottom);
    }
}
