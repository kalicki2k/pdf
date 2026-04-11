<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use PHPUnit\Framework\TestCase;

final class PageOptionsTest extends TestCase
{
    public function testItCarriesPageConfigurationValues(): void
    {
        $options = new PageOptions(
            pageSize: PageSize::A5(),
            orientation: PageOrientation::LANDSCAPE,
            margin: Margin::all(24.0),
            backgroundColor: Color::hex('#f5f5f5'),
            label: 'cover',
            name: 'title-page',
        );

        self::assertSame(PageOrientation::LANDSCAPE, $options->orientation);
        self::assertSame(24.0, $options->margin?->top);
        self::assertSame(ColorSpace::RGB, $options->backgroundColor?->space);
        self::assertSame([245 / 255, 245 / 255, 245 / 255], $options->backgroundColor?->components());
        self::assertSame('cover', $options->label);
        self::assertSame('title-page', $options->name);
        self::assertSame(PageSize::A5()->width(), $options->pageSize?->width());
        self::assertSame(PageSize::A5()->height(), $options->pageSize?->height());
    }
}
