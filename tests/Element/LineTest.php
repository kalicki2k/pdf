<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Page\Content\Instruction\LineInstruction;

use function Kalle\Pdf\Tests\Support\writeContentInstructionToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class LineTest extends TestCase
{
    #[Test]
    public function it_renders_a_simple_stroked_line(): void
    {
        $line = new LineInstruction(10, 20, 100, 20);

        self::assertSame(
            "q\n"
            . "1 w\n"
            . "10 20 m\n"
            . "100 20 l\n"
            . "S\n"
            . 'Q',
            writeContentInstructionToString($line),
        );
    }

    #[Test]
    public function it_renders_optional_stroke_color_and_graphics_state(): void
    {
        $line = new LineInstruction(10, 20, 100, 20, 2.5, '1 0 0 RG', 'GS1');

        self::assertSame(
            "q\n"
            . "1 0 0 RG\n"
            . "/GS1 gs\n"
            . "2.5 w\n"
            . "10 20 m\n"
            . "100 20 l\n"
            . "S\n"
            . 'Q',
            writeContentInstructionToString($line),
        );
    }
}
