<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Table\Style;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableBorderTest extends TestCase
{
    #[Test]
    public function it_builds_border_variants_and_reports_enabled_sides(): void
    {
        $all = TableBorder::all(1.5, Color::rgb(255, 0, 0), Opacity::both(0.4));
        $horizontal = TableBorder::horizontal();
        $vertical = TableBorder::vertical();
        $only = TableBorder::only(['left', 'bottom']);

        self::assertSame(1.5, $all->width);
        self::assertSame('1 0 0 RG', $all->color?->renderStrokingOperator());
        self::assertSame('<< /ca 0.4 /CA 0.4 >>', $all->opacity?->renderExtGStateDictionary());
        self::assertTrue($all->isAll());
        self::assertTrue($all->hasAnySide());
        self::assertTrue($all->isDefinedFor('top'));
        self::assertTrue($all->isEnabled('right'));
        self::assertTrue($all->isDefinedFor('bottom'));
        self::assertTrue($all->isEnabled('top'));
        self::assertTrue($all->isEnabled('left'));

        self::assertTrue($horizontal->isDefinedFor('top'));
        self::assertFalse($horizontal->isDefinedFor('right'));
        self::assertTrue($horizontal->isEnabled('bottom'));

        self::assertFalse($vertical->isDefinedFor('top'));
        self::assertTrue($vertical->isDefinedFor('left'));
        self::assertTrue($vertical->isEnabled('right'));

        self::assertFalse($only->isAll());
        self::assertTrue($only->hasAnySide());
        self::assertTrue($only->isDefinedFor('left'));
        self::assertTrue($only->isEnabled('bottom'));
        self::assertFalse($only->isDefinedFor('top'));

        self::assertNull(TableBorder::none());
    }

    #[Test]
    public function it_rejects_invalid_border_values_and_sides(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table border width must be greater than zero.');

        TableBorder::all(0);
    }

    #[Test]
    public function it_rejects_unsupported_border_side_queries(): void
    {
        $border = TableBorder::all();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported border side 'diagonal'.");

        $border->isDefinedFor('diagonal');
    }

    #[Test]
    public function it_rejects_unsupported_enabled_side_queries(): void
    {
        $border = TableBorder::all();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported border side 'diagonal'.");

        $border->isEnabled('diagonal');
    }
}
