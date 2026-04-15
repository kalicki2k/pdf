<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsStyle;
use PHPUnit\Framework\TestCase;

final class TableOfContentsValueObjectTest extends TestCase
{
    public function testPlacementResolvesStartEndAndAfterPage(): void
    {
        self::assertSame(0, TableOfContentsPlacement::start()->insertionIndex(4));
        self::assertSame(4, TableOfContentsPlacement::end()->insertionIndex(4));
        self::assertSame(2, TableOfContentsPlacement::afterPage(2)->insertionIndex(4));
    }

    public function testPlacementRejectsInvalidPageNumbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents insertion page must be greater than zero.');

        TableOfContentsPlacement::afterPage(0);
    }

    public function testPlacementRejectsOutOfBoundsPageNumbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents insertion page 3 is out of bounds for a document with 2 pages.');

        TableOfContentsPlacement::afterPage(3)->insertionIndex(2);
    }

    public function testStyleExposesDefaults(): void
    {
        $style = new TableOfContentsStyle();

        self::assertSame(TableOfContentsLeaderStyle::DOTS, $style->leaderStyle);
        self::assertSame(0.0, $style->entrySpacing);
        self::assertSame(8.0, $style->pageNumberGap);
        self::assertSame(14.0, $style->titleSpacingAfter);
    }

    public function testStyleRejectsNegativeValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents entry spacing must be zero or greater.');

        new TableOfContentsStyle(entrySpacing: -1.0);
    }

    public function testOptionsExposeDefaults(): void
    {
        $options = new TableOfContentsOptions();

        self::assertSame('Contents', $options->title);
        self::assertSame('Helvetica', $options->fontName);
        self::assertSame(18.0, $options->titleSize);
        self::assertSame(12.0, $options->entrySize);
        self::assertEquals(TableOfContentsPlacement::end(), $options->placement);
        self::assertEquals(new TableOfContentsStyle(), $options->style);
    }

    public function testOptionsRejectEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents title must not be empty.');

        new TableOfContentsOptions(title: '');
    }
}
