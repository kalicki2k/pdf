<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use InvalidArgumentException;
use Kalle\Pdf\Layout\TableOfContentsLeaderStyle;
use Kalle\Pdf\Layout\TableOfContentsStyle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableOfContentsStyleTest extends TestCase
{
    #[Test]
    public function it_exposes_default_table_of_contents_style_values(): void
    {
        $style = new TableOfContentsStyle();

        self::assertSame(TableOfContentsLeaderStyle::DOTS, $style->leaderStyle);
        self::assertSame(0.0, $style->entrySpacing);
        self::assertSame(8.0, $style->pageNumberGap);
    }

    #[Test]
    public function it_exposes_custom_table_of_contents_style_values(): void
    {
        $style = new TableOfContentsStyle(
            leaderStyle: TableOfContentsLeaderStyle::DASHES,
            entrySpacing: 3.5,
            pageNumberGap: 12.0,
        );

        self::assertSame(TableOfContentsLeaderStyle::DASHES, $style->leaderStyle);
        self::assertSame(3.5, $style->entrySpacing);
        self::assertSame(12.0, $style->pageNumberGap);
    }

    #[Test]
    public function it_rejects_negative_entry_spacing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents entry spacing must be zero or greater.');

        new TableOfContentsStyle(entrySpacing: -1.0);
    }

    #[Test]
    public function it_rejects_negative_page_number_gaps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents page number gap must be zero or greater.');

        new TableOfContentsStyle(pageNumberGap: -0.1);
    }
}
