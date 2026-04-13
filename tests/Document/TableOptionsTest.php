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
        $options = new TableOptions();

        self::assertNull($options->caption);
        self::assertNull($options->placement);
        self::assertEquals(new CellPadding(4.0, 4.0, 4.0, 4.0), $options->cellPadding);
        self::assertEquals(new Border(0.5, 0.5, 0.5, 0.5), $options->border);
        self::assertEquals(new TextOptions(fontSize: 12.0, lineHeight: 14.4), $options->textOptions);
        self::assertFalse($options->repeatHeaderOnPageBreak);
        self::assertFalse($options->repeatFooterOnPageBreak);
    }

    public function testItProvidesImmutableWithMethods(): void
    {
        $caption = TableCaption::text('Quarterly overview');
        $placement = TablePlacement::at(48.0, 460.0, 220.0);
        $padding = CellPadding::symmetric(2.0, 3.0);
        $border = Border::all(1.0);
        $text = new TextOptions(fontSize: 9.0, lineHeight: 12.0);

        $options = (new TableOptions())
            ->withCaption($caption)
            ->withPlacement($placement)
            ->withCellPadding($padding)
            ->withBorder($border)
            ->withTextOptions($text)
            ->withRepeatedHeaderOnPageBreak()
            ->withRepeatedFooterOnPageBreak();

        self::assertSame($caption, $options->caption);
        self::assertSame($placement, $options->placement);
        self::assertSame($padding, $options->cellPadding);
        self::assertSame($border, $options->border);
        self::assertSame($text, $options->textOptions);
        self::assertTrue($options->repeatHeaderOnPageBreak);
        self::assertTrue($options->repeatFooterOnPageBreak);
    }
}
