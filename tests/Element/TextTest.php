<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Element\Text;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    #[Test]
    public function it_renders_text_content_with_pdf_commands_and_escaped_text(): void
    {
        $text = new Text(3, '(Hello \\(PDF\\)\\n)', 10, 20, 'F1', 12, 30, null, null, false, false, 'P');

        self::assertSame(
            "q\n"
            . "/P << /MCID 3 >> BDC\n"
            . "BT\n"
            . "/F1 12 Tf\n"
            . "10 20 Td\n"
            . "(Hello \\(PDF\\)\\n) Tj\n"
            . "ET\n"
            . "EMC\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_renders_pre_encoded_unicode_text_operands(): void
    {
        $text = new Text(0, '<FEFF6F22>', 1.5, 2.5, 'F2', 9, 20, null, null, false, false, 'Span');

        self::assertSame(
            "q\n"
            . "/Span << /MCID 0 >> BDC\n"
            . "BT\n"
            . "/F2 9 Tf\n"
            . "1.5 2.5 Td\n"
            . "<FEFF6F22> Tj\n"
            . "ET\n"
            . "EMC\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_renders_unstructured_text_without_marked_content_commands(): void
    {
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12, 30);

        self::assertSame(
            "q\n"
            . "BT\n"
            . "/F1 12 Tf\n"
            . "10 20 Td\n"
            . "(Hello) Tj\n"
            . "ET\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_renders_an_optional_graphics_state_before_the_text_operand(): void
    {
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12, 30, null, 'GS1');

        self::assertSame(
            "q\n"
            . "BT\n"
            . "/F1 12 Tf\n"
            . "10 20 Td\n"
            . "/GS1 gs\n"
            . "(Hello) Tj\n"
            . "ET\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_renders_an_optional_color_operator_before_the_text_operand(): void
    {
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12, 30, '1 0 0 rg');

        self::assertSame(
            "q\n"
            . "BT\n"
            . "/F1 12 Tf\n"
            . "10 20 Td\n"
            . "1 0 0 rg\n"
            . "(Hello) Tj\n"
            . "ET\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_renders_underline_and_strikethrough_as_filled_rectangles(): void
    {
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12, 30, '1 0 0 rg', null, true, true);

        self::assertSame(
            "q\n"
            . "BT\n"
            . "/F1 12 Tf\n"
            . "10 20 Td\n"
            . "1 0 0 rg\n"
            . "(Hello) Tj\n"
            . "ET\n"
            . "10 17.84 30 0.6 re f\n"
            . "10 23.6 30 0.6 re f\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_skips_text_decorations_when_the_rendered_width_is_non_positive(): void
    {
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12, 0, '1 0 0 rg', null, true, true);

        self::assertSame(
            "q\n"
            . "BT\n"
            . "/F1 12 Tf\n"
            . "10 20 Td\n"
            . "1 0 0 rg\n"
            . "(Hello) Tj\n"
            . "ET\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_applies_decoration_insets_to_underlines(): void
    {
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12, 30, null, null, true, false, null, 2.5, 4.5);

        self::assertSame(
            "q\n"
            . "BT\n"
            . "/F1 12 Tf\n"
            . "10 20 Td\n"
            . "(Hello) Tj\n"
            . "ET\n"
            . "12.5 17.84 23 0.6 re f\n"
            . 'Q',
            $text->render(),
        );
    }
}
