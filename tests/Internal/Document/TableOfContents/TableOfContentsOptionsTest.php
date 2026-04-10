<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\TableOfContents;

use InvalidArgumentException;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsStyle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableOfContentsOptionsTest extends TestCase
{
    #[Test]
    public function it_exposes_defaults_for_table_of_contents_options(): void
    {
        $options = new TableOfContentsOptions();

        self::assertSame('Contents', $options->title);
        self::assertSame('Helvetica', $options->baseFont);
        self::assertSame(18, $options->titleSize);
        self::assertSame(12, $options->entrySize);
        self::assertSame(20.0, $options->margin);
        self::assertEquals(TableOfContentsPlacement::end(), $options->placement);
        self::assertFalse($options->useLogicalPageNumbers);
        self::assertEquals(new TableOfContentsStyle(), $options->style);
    }

    #[Test]
    public function it_exposes_custom_table_of_contents_options(): void
    {
        $placement = TableOfContentsPlacement::start();
        $style = new TableOfContentsStyle(leaderStyle: TableOfContentsLeaderStyle::DASHES);

        $options = new TableOfContentsOptions(
            title: 'Inhalt',
            baseFont: 'Times-Roman',
            titleSize: 20,
            entrySize: 11,
            margin: 12.5,
            placement: $placement,
            useLogicalPageNumbers: true,
            style: $style,
        );

        self::assertSame('Inhalt', $options->title);
        self::assertSame('Times-Roman', $options->baseFont);
        self::assertSame(20, $options->titleSize);
        self::assertSame(11, $options->entrySize);
        self::assertSame(12.5, $options->margin);
        self::assertSame($placement, $options->placement);
        self::assertTrue($options->useLogicalPageNumbers);
        self::assertSame($style, $options->style);
    }

    #[Test]
    public function it_rejects_non_positive_title_sizes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents title size must be greater than zero.');

        new TableOfContentsOptions(titleSize: 0);
    }

    #[Test]
    public function it_rejects_non_positive_entry_sizes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents entry size must be greater than zero.');

        new TableOfContentsOptions(entrySize: 0);
    }

    #[Test]
    public function it_rejects_negative_margins(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents margin must be zero or greater.');

        new TableOfContentsOptions(margin: -0.1);
    }
}
