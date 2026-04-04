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
        $text = new Text(3, '(Hello \\(PDF\\)\\n)', 10, 20, 'F1', 12, null, null, 'P');

        self::assertSame(
            "q\n"
            . "BT\n"
            . "/F1 12 Tf\n"
            . "10 20 Td\n"
            . "/P << /MCID 3 >> BDC\n"
            . "(Hello \\(PDF\\)\\n) Tj\n"
            . "EMC\n"
            . "ET\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_renders_pre_encoded_unicode_text_operands(): void
    {
        $text = new Text(0, '<FEFF6F22>', 1.5, 2.5, 'F2', 9, null, null, 'Span');

        self::assertSame(
            "q\n"
            . "BT\n"
            . "/F2 9 Tf\n"
            . "1.5 2.5 Td\n"
            . "/Span << /MCID 0 >> BDC\n"
            . "<FEFF6F22> Tj\n"
            . "EMC\n"
            . "ET\n"
            . 'Q',
            $text->render(),
        );
    }

    #[Test]
    public function it_renders_unstructured_text_without_marked_content_commands(): void
    {
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12);

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
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12, null, 'GS1');

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
        $text = new Text(null, '(Hello)', 10, 20, 'F1', 12, '1 0 0 rg');

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
}
