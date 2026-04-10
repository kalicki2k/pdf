<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Layout\Table\Style\CellStyle;
use Kalle\Pdf\Layout\Table\Style\HeaderStyle;
use Kalle\Pdf\Layout\Table\Style\RowStyle;
use Kalle\Pdf\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Layout\Table\Support\ResolvedBorderSide;
use Kalle\Pdf\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableStyleResolverTest extends TestCase
{
    #[Test]
    public function it_merges_table_style_values(): void
    {
        $resolver = new TableStyleResolver();
        $basePadding = TablePadding::all(2);
        $overrideBorder = TableBorder::all(1.5, Color::rgb(255, 0, 0));

        $merged = $resolver->mergeTableStyle(
            new TableStyle(
                padding: $basePadding,
                verticalAlign: VerticalAlign::TOP,
                fillColor: Color::gray(0.9),
            ),
            new TableStyle(
                border: $overrideBorder,
                verticalAlign: VerticalAlign::MIDDLE,
                textColor: Color::rgb(0, 0, 255),
            ),
        );

        self::assertSame($basePadding, $merged->padding);
        self::assertSame($overrideBorder, $merged->border);
        self::assertSame(VerticalAlign::MIDDLE, $merged->verticalAlign);
        self::assertSame('0.9 g', $merged->fillColor?->renderNonStrokingOperator());
        self::assertSame('0 0 1 rg', $merged->textColor?->renderNonStrokingOperator());
    }

    #[Test]
    public function it_merges_row_and_header_styles(): void
    {
        $resolver = new TableStyleResolver();
        $basePadding = TablePadding::all(1);
        $overrideOpacity = Opacity::both(0.5);

        $mergedRow = $resolver->mergeRowStyle(
            new RowStyle(
                horizontalAlign: HorizontalAlign::LEFT,
                verticalAlign: VerticalAlign::TOP,
                padding: $basePadding,
            ),
            new RowStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                opacity: $overrideOpacity,
            ),
        );

        $mergedHeader = $resolver->mergeHeaderStyle(
            new HeaderStyle(
                horizontalAlign: HorizontalAlign::RIGHT,
                fillColor: Color::gray(0.8),
            ),
            new HeaderStyle(
                textColor: Color::rgb(0, 0, 255),
            ),
        );

        self::assertSame(HorizontalAlign::CENTER, $mergedRow->horizontalAlign);
        self::assertSame(VerticalAlign::TOP, $mergedRow->verticalAlign);
        self::assertSame($basePadding, $mergedRow->padding);
        self::assertSame('<< /ca 0.5 /CA 0.5 >>', $mergedRow->opacity?->renderExtGStateDictionary());

        self::assertInstanceOf(HeaderStyle::class, $mergedHeader);
        self::assertSame(HorizontalAlign::RIGHT, $mergedHeader->horizontalAlign);
        self::assertSame('0.8 g', $mergedHeader->fillColor?->renderNonStrokingOperator());
        self::assertSame('0 0 1 rg', $mergedHeader->textColor?->renderNonStrokingOperator());
    }

    #[Test]
    public function it_resolves_cell_style_with_header_row_and_table_fallbacks(): void
    {
        $resolver = new TableStyleResolver();
        $tableBorder = TableBorder::all(1.0, Color::gray(0.7));
        $rowBorder = TableBorder::horizontal(2.0, Color::rgb(255, 0, 0), Opacity::both(0.4));
        $cellBorder = TableBorder::only(['left'], 0.5, Color::rgb(0, 0, 255));
        $rowPadding = TablePadding::all(3);
        $cellPadding = TablePadding::only(top: 1, right: 2, bottom: 3, left: 4);

        $resolvedFromRow = $resolver->resolveCellStyle(
            new TableStyle(
                padding: TablePadding::all(9),
                border: $tableBorder,
                verticalAlign: VerticalAlign::BOTTOM,
                fillColor: Color::gray(0.9),
                textColor: Color::gray(0.1),
            ),
            new RowStyle(
                horizontalAlign: HorizontalAlign::RIGHT,
                verticalAlign: VerticalAlign::MIDDLE,
                padding: $rowPadding,
                fillColor: Color::gray(0.8),
                textColor: Color::rgb(10, 20, 30),
                opacity: Opacity::both(0.6),
                border: $rowBorder,
            ),
            new HeaderStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                fillColor: Color::gray(0.5),
            ),
            new TableCell(
                text: 'value',
                style: new CellStyle(
                    padding: $cellPadding,
                    textColor: Color::rgb(0, 0, 255),
                    border: $cellBorder,
                ),
            ),
            false,
        );

        $resolvedFromHeader = $resolver->resolveCellStyle(
            new TableStyle(),
            new RowStyle(horizontalAlign: HorizontalAlign::RIGHT),
            new HeaderStyle(horizontalAlign: HorizontalAlign::CENTER),
            new TableCell('header'),
            true,
        );

        $resolvedDefaults = $resolver->resolveCellStyle(
            new TableStyle(),
            null,
            null,
            new TableCell('plain'),
            false,
        );

        self::assertSame($cellPadding, $resolvedFromRow->padding);
        self::assertSame('0.8 g', $resolvedFromRow->fillColor?->renderNonStrokingOperator());
        self::assertSame('0 0 1 rg', $resolvedFromRow->textColor?->renderNonStrokingOperator());
        self::assertSame(VerticalAlign::MIDDLE, $resolvedFromRow->verticalAlign);
        self::assertSame(HorizontalAlign::RIGHT, $resolvedFromRow->horizontalAlign);
        self::assertSame('<< /ca 0.6 /CA 0.6 >>', $resolvedFromRow->opacity?->renderExtGStateDictionary());
        self::assertSame($rowBorder, $resolvedFromRow->rowBorder);
        self::assertSame($cellBorder, $resolvedFromRow->cellBorder);

        self::assertSame(HorizontalAlign::CENTER, $resolvedFromHeader->horizontalAlign);
        self::assertSame(VerticalAlign::TOP, $resolvedDefaults->verticalAlign);
        self::assertSame(HorizontalAlign::LEFT, $resolvedDefaults->horizontalAlign);
        self::assertSame(0.0, $resolvedDefaults->padding->top);
        self::assertNull($resolvedDefaults->fillColor);
    }

    #[Test]
    public function it_resolves_border_sides_with_priority_and_defaults(): void
    {
        $resolver = new TableStyleResolver();
        $defaultBorder = TableBorder::all();
        $rowBorder = TableBorder::horizontal(2.5, null, Opacity::both(0.25));
        $cellBorder = TableBorder::only(['top', 'left'], 0.75, Color::rgb(255, 0, 0));
        $resolvedTop = $resolver->resolveBorderSide('top', $defaultBorder, $rowBorder, $cellBorder);
        $resolvedBottom = $resolver->resolveBorderSide('bottom', $defaultBorder, $rowBorder, null);
        $resolvedRight = $resolver->resolveBorderSide('right', $defaultBorder, null, null);
        $resolvedTopWithoutOpacity = $resolver->resolveBorderSide(
            'top',
            TableBorder::all(1.25),
            null,
            TableBorder::only(['top'], 0.5, Color::gray(0.3)),
        );
        $resolvedMissing = $resolver->resolveBorderSide('left', null, null, null);

        self::assertInstanceOf(ResolvedBorderSide::class, $resolvedTop);
        self::assertSame(0.75, $resolvedTop->width);
        self::assertSame('1 0 0 RG', $resolvedTop->color?->renderStrokingOperator());
        self::assertSame('<< /ca 0.25 /CA 0.25 >>', $resolvedTop->opacity?->renderExtGStateDictionary());

        self::assertInstanceOf(ResolvedBorderSide::class, $resolvedBottom);
        self::assertSame(2.5, $resolvedBottom->width);
        self::assertNull($resolvedBottom->color);
        self::assertSame('<< /ca 0.25 /CA 0.25 >>', $resolvedBottom->opacity?->renderExtGStateDictionary());

        self::assertInstanceOf(ResolvedBorderSide::class, $resolvedRight);
        self::assertSame(1.0, $resolvedRight->width);
        self::assertNull($resolvedRight->color);
        self::assertNull($resolvedRight->opacity);

        self::assertInstanceOf(ResolvedBorderSide::class, $resolvedTopWithoutOpacity);
        self::assertSame(0.5, $resolvedTopWithoutOpacity->width);
        self::assertSame('0.3 G', $resolvedTopWithoutOpacity->color?->renderStrokingOperator());
        self::assertNull($resolvedTopWithoutOpacity->opacity);

        self::assertNull($resolvedMissing);
    }

    #[Test]
    public function it_compares_borders_for_equivalence(): void
    {
        $resolver = new TableStyleResolver();
        $top = new ResolvedBorderSide(1.0, Color::gray(0.2), Opacity::both(0.3));
        $right = new ResolvedBorderSide(1.0, Color::gray(0.2), Opacity::both(0.3));
        $bottom = new ResolvedBorderSide(1.0, Color::gray(0.2), Opacity::both(0.3));
        $left = new ResolvedBorderSide(2.0, Color::gray(0.2), Opacity::both(0.3));

        self::assertTrue($resolver->bordersAreEquivalent($top, $right, $bottom, $right));
        self::assertFalse($resolver->bordersAreEquivalent($top, $right, $bottom, $left));
    }
}
