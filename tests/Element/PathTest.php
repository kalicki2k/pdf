<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Page\Content\Instruction\PathInstruction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    #[Test]
    public function it_renders_a_stroked_path(): void
    {
        $path = new PathInstruction(['20 200 m', '60 240 l', '100 200 l', '60 160 l', 'h'], 1.5, '1 0 0 RG');

        self::assertSame(
            "q\n"
            . "1 0 0 RG\n"
            . "1.5 w\n"
            . "20 200 m\n"
            . "60 240 l\n"
            . "100 200 l\n"
            . "60 160 l\n"
            . "h\n"
            . "S\n"
            . 'Q',
            $path->render(),
        );
    }

    #[Test]
    public function it_renders_a_filled_path(): void
    {
        $path = new PathInstruction(['20 200 m', '60 240 l', '100 200 l', '60 160 l', 'h'], null, null, '0.9 g', null, 'f');

        self::assertSame(
            "q\n"
            . "0.9 g\n"
            . "20 200 m\n"
            . "60 240 l\n"
            . "100 200 l\n"
            . "60 160 l\n"
            . "h\n"
            . "f\n"
            . 'Q',
            $path->render(),
        );
    }

    #[Test]
    public function it_renders_a_path_with_curves(): void
    {
        $path = new PathInstruction(['100 130 m', '116.568542 130 130 116.568542 130 100 c'], 1.0, '1 0 0 RG');

        self::assertSame(
            "q\n"
            . "1 0 0 RG\n"
            . "1 w\n"
            . "100 130 m\n"
            . "116.568542 130 130 116.568542 130 100 c\n"
            . "S\n"
            . 'Q',
            $path->render(),
        );
    }

    #[Test]
    public function it_renders_a_path_with_a_graphics_state_only(): void
    {
        $path = new PathInstruction(['10 10 m', '20 20 l'], null, null, null, 'GS1');

        self::assertSame(
            "q\n"
            . "/GS1 gs\n"
            . "10 10 m\n"
            . "20 20 l\n"
            . "S\n"
            . 'Q',
            $path->render(),
        );
    }

    #[Test]
    public function it_formats_zero_without_a_decimal_point(): void
    {
        self::assertSame('0', PathInstruction::formatNumber(0.0));
    }
}
