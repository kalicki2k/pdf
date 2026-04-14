<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageBox;
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
            cropBox: PageBox::fromPoints(10.0, 20.0, 300.0, 400.0),
            bleedBox: PageBox::fromPoints(11.0, 21.0, 301.0, 401.0),
            trimBox: PageBox::fromPoints(12.0, 22.0, 302.0, 402.0),
            artBox: PageBox::fromPoints(13.0, 23.0, 303.0, 403.0),
        );

        self::assertSame(PageOrientation::LANDSCAPE, $options->orientation);
        self::assertSame(24.0, $options->margin?->top);
        self::assertSame(ColorSpace::RGB, $options->backgroundColor?->space);
        self::assertSame([245 / 255, 245 / 255, 245 / 255], $options->backgroundColor?->components());
        self::assertSame('cover', $options->label);
        self::assertSame('title-page', $options->name);
        self::assertSame(PageSize::A5()->width(), $options->pageSize?->width());
        self::assertSame(PageSize::A5()->height(), $options->pageSize?->height());
        self::assertSame(10.0, $options->cropBox?->left);
        self::assertSame(20.0, $options->cropBox?->bottom);
        self::assertSame(300.0, $options->cropBox?->right);
        self::assertSame(400.0, $options->cropBox?->top);
        self::assertSame(11.0, $options->bleedBox?->left);
        self::assertSame(21.0, $options->bleedBox?->bottom);
        self::assertSame(301.0, $options->bleedBox?->right);
        self::assertSame(401.0, $options->bleedBox?->top);
        self::assertSame(12.0, $options->trimBox?->left);
        self::assertSame(22.0, $options->trimBox?->bottom);
        self::assertSame(302.0, $options->trimBox?->right);
        self::assertSame(402.0, $options->trimBox?->top);
        self::assertSame(13.0, $options->artBox?->left);
        self::assertSame(23.0, $options->artBox?->bottom);
        self::assertSame(303.0, $options->artBox?->right);
        self::assertSame(403.0, $options->artBox?->top);
    }
}
