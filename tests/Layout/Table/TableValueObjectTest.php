<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout\Table;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableCellContent;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Layout\PositionMode;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\ColumnWidth;
use Kalle\Pdf\Layout\Table\VerticalAlign;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class TableValueObjectTest extends TestCase
{
    public function testColumnWidthRejectsNonPositiveValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ColumnWidth::fixed(0.0);
    }

    public function testColumnWidthCanRepresentAutomaticWidths(): void
    {
        $width = ColumnWidth::auto();

        self::assertTrue($width->isAuto());
        self::assertFalse($width->isFixed());
        self::assertFalse($width->isProportional());
    }

    public function testCellPaddingReportsCombinedInsets(): void
    {
        $padding = CellPadding::symmetric(3.0, 5.0);

        self::assertSame(10.0, $padding->horizontal());
        self::assertSame(6.0, $padding->vertical());
    }

    public function testBorderVisibilityDependsOnAnySideWidth(): void
    {
        self::assertFalse(Border::none()->isVisible());
        self::assertTrue(Border::all(0.5)->isVisible());
    }

    public function testTablePlacementRejectsNonPositiveWidths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TablePlacement::relative(left: 30.0, width: 0.0);
    }

    public function testTablePlacementRejectsMissingWidthWithoutBothHorizontalInsets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table placement requires a width unless both left and right are provided.');

        TablePlacement::relative(left: 30.0);
    }

    public function testTablePlacementCanCarryRelativeTopCoordinates(): void
    {
        $placement = TablePlacement::relative(left: 30.0, top: 420.0, width: 180.0);

        self::assertSame(PositionMode::RELATIVE, $placement->positionMode);
        self::assertSame(30.0, $placement->left);
        self::assertSame(420.0, $placement->top);
        self::assertSame(180.0, $placement->width);
    }

    public function testTablePlacementCanOmitWidthWhenBothHorizontalInsetsAreProvided(): void
    {
        $placement = TablePlacement::relative(left: 30.0, right: 20.0, top: 12.0);

        self::assertSame(30.0, $placement->left);
        self::assertSame(20.0, $placement->right);
        self::assertNull($placement->width);
    }

    public function testTableCellCanCarryCellSpecificOverrides(): void
    {
        $cell = TableCell::text('Value')
            ->withBackgroundColor(Color::hex('#ffeecc'))
            ->withVerticalAlign(VerticalAlign::MIDDLE)
            ->withHorizontalAlign(TextAlign::RIGHT)
            ->withNoWrap()
            ->withPadding(CellPadding::symmetric(2.0, 6.0))
            ->withBorder(new Border(1.0, 0.0, 1.0, 0.0));

        self::assertSame(VerticalAlign::MIDDLE, $cell->verticalAlign);
        self::assertSame(TextAlign::RIGHT, $cell->horizontalAlign);
        self::assertTrue($cell->noWrap);
        self::assertEquals(Color::hex('#ffeecc'), $cell->backgroundColor);
        self::assertEquals(CellPadding::symmetric(2.0, 6.0), $cell->padding);
        self::assertEquals(new Border(1.0, 0.0, 1.0, 0.0), $cell->border);
    }

    public function testTableCellContentSupportsRichTextSegments(): void
    {
        $content = TableCellContent::segments(
            TextSegment::plain('Read '),
            TextSegment::link('docs', TextLink::externalUrl('https://example.com/docs')),
        );

        self::assertTrue($content->isRichText());
        self::assertSame('Read docs', $content->plainText);
        self::assertCount(2, $content->segments);
    }

    public function testTableCellMutatorsPreserveRichTextContent(): void
    {
        $cell = TableCell::segments(
            TextSegment::plain('Read '),
            TextSegment::link('docs', TextLink::externalUrl('https://example.com/docs')),
        )
            ->withColspan(2)
            ->withRowspan(3)
            ->withBackgroundColor(Color::hex('#ffeecc'))
            ->withVerticalAlign(VerticalAlign::MIDDLE)
            ->withHorizontalAlign(TextAlign::RIGHT)
            ->withNoWrap()
            ->withPadding(CellPadding::symmetric(2.0, 6.0))
            ->withBorder(new Border(1.0, 0.0, 1.0, 0.0));

        self::assertTrue($cell->content->isRichText());
        self::assertSame('Read docs', $cell->text);
        self::assertCount(2, $cell->content->segments);
        self::assertSame(2, $cell->colspan);
        self::assertSame(3, $cell->rowspan);
        self::assertTrue($cell->noWrap);
    }
}
