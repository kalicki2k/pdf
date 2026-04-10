<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Table\Style;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TablePaddingTest extends TestCase
{
    #[Test]
    public function it_builds_padding_variants_and_resolves_sums(): void
    {
        $all = TablePadding::all(5);
        $symmetric = TablePadding::symmetric(8, 3);
        $only = TablePadding::only(top: 1, right: 2, bottom: 3, left: 4);

        self::assertSame(5.0, $all->top);
        self::assertSame(5.0, $all->right);
        self::assertSame(5.0, $all->bottom);
        self::assertSame(5.0, $all->left);

        self::assertSame(3.0, $symmetric->top);
        self::assertSame(8.0, $symmetric->right);
        self::assertSame(3.0, $symmetric->bottom);
        self::assertSame(8.0, $symmetric->left);

        self::assertSame(1.0, $only->top);
        self::assertSame(2.0, $only->right);
        self::assertSame(3.0, $only->bottom);
        self::assertSame(4.0, $only->left);
        self::assertSame(6.0, $only->horizontal());
        self::assertSame(4.0, $only->vertical());
    }

    #[Test]
    public function it_rejects_negative_padding_values(): void
    {
        $cases = [
            fn (): TablePadding => TablePadding::all(-1),
            fn (): TablePadding => TablePadding::symmetric(-1, 2),
            fn (): TablePadding => TablePadding::only(left: -1),
        ];

        foreach ($cases as $callback) {
            try {
                $callback();
                self::fail('Expected InvalidArgumentException for negative padding.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Table padding values must be zero or greater.', $exception->getMessage());
            }
        }
    }
}
