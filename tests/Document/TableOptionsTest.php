<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class TableOptionsTest extends TestCase
{
    public function testItExposesCurrentTableDefaults(): void
    {
        $options = TableOptions::make();

        self::assertNull($options->caption);
        self::assertNull($options->placement);
        self::assertEquals(new CellPadding(4.0, 4.0, 4.0, 4.0), $options->cellPadding);
        self::assertEquals(new Border(0.5, 0.5, 0.5, 0.5), $options->border);
        self::assertEquals(TextOptions::make(fontSize: 12.0, lineHeight: 14.4), $options->textOptions);
        self::assertSame(0.0, $options->spacingBefore);
        self::assertSame(0.0, $options->spacingAfter);
        self::assertFalse($options->repeatHeaderOnPageBreak);
        self::assertFalse($options->repeatFooterOnPageBreak);
    }

    public function testItProvidesImmutableWithMethods(): void
    {
        $caption = TableCaption::text('Quarterly overview');
        $placement = TablePlacement::absolute(left: 48.0, top: 381.89, width: 220.0);
        $padding = CellPadding::symmetric(2.0, 3.0);
        $border = Border::all(1.0);
        $text = TextOptions::make(fontSize: 9.0, lineHeight: 12.0);

        $options = (TableOptions::make())
            ->withCaption($caption)
            ->withPlacement($placement)
            ->withCellPadding($padding)
            ->withBorder($border)
            ->withTextOptions($text)
            ->withSpacingBefore(8.0)
            ->withSpacingAfter(12.0)
            ->withRepeatedHeaderOnPageBreak()
            ->withRepeatedFooterOnPageBreak();

        self::assertSame($caption, $options->caption);
        self::assertSame($placement, $options->placement);
        self::assertSame($padding, $options->cellPadding);
        self::assertSame($border, $options->border);
        self::assertSame($text, $options->textOptions);
        self::assertSame(8.0, $options->spacingBefore);
        self::assertSame(12.0, $options->spacingAfter);
        self::assertTrue($options->repeatHeaderOnPageBreak);
        self::assertTrue($options->repeatFooterOnPageBreak);
    }

    public function testMakeFactoryBuildsTableOptions(): void
    {
        $caption = TableCaption::text('Quarterly overview');
        $placement = TablePlacement::absolute(left: 48.0, top: 381.89, width: 220.0);
        $padding = CellPadding::symmetric(2.0, 3.0);
        $border = Border::all(1.0);
        $text = TextOptions::make(fontSize: 9.0, lineHeight: 12.0);

        $options = TableOptions::make(
            border: $border,
            textOptions: $text,
            caption: $caption,
            placement: $placement,
            cellPadding: $padding,
            spacingBefore: 8.0,
            spacingAfter: 12.0,
            repeatHeaderOnPageBreak: true,
            repeatFooterOnPageBreak: true,
        );

        self::assertSame($caption, $options->caption);
        self::assertSame($placement, $options->placement);
        self::assertSame($padding, $options->cellPadding);
        self::assertSame($border, $options->border);
        self::assertSame($text, $options->textOptions);
        self::assertSame(8.0, $options->spacingBefore);
        self::assertSame(12.0, $options->spacingAfter);
        self::assertTrue($options->repeatHeaderOnPageBreak);
        self::assertTrue($options->repeatFooterOnPageBreak);
    }
}
