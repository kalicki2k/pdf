<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Internal\Page\Content\Instruction\RectangleInstruction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RectangleTest extends TestCase
{
    #[Test]
    public function it_renders_a_stroked_rectangle(): void
    {
        $rectangle = new RectangleInstruction(10, 20, 100, 40);

        self::assertSame(
            "q\n"
            . "10 20 100 40 re\n"
            . "S\n"
            . 'Q',
            $rectangle->render(),
        );
    }

    #[Test]
    public function it_renders_a_filled_rectangle(): void
    {
        $rectangle = new RectangleInstruction(10, 20, 100, 40, null, null, '0.5 g');

        self::assertSame(
            "q\n"
            . "0.5 g\n"
            . "10 20 100 40 re\n"
            . "f\n"
            . 'Q',
            $rectangle->render(),
        );
    }

    #[Test]
    public function it_renders_a_filled_and_stroked_rectangle(): void
    {
        $rectangle = new RectangleInstruction(10, 20, 100, 40, 2.5, '1 0 0 RG', '0.5 g', 'GS1');

        self::assertSame(
            "q\n"
            . "1 0 0 RG\n"
            . "0.5 g\n"
            . "/GS1 gs\n"
            . "2.5 w\n"
            . "10 20 100 40 re\n"
            . "B\n"
            . 'Q',
            $rectangle->render(),
        );
    }
}
