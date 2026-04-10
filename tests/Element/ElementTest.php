<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Internal\Page\Content\Instruction\ContentInstruction;
use Kalle\Pdf\Internal\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ElementTest extends TestCase
{
    #[Test]
    public function it_sets_the_position_and_returns_itself(): void
    {
        $element = new class () extends ContentInstruction {
            public function render(): string
            {
                return 'dummy';
            }
        };

        $result = $element->setPosition(12.5, 34.75);

        self::assertSame($element, $result);
        self::assertSame(12.5, $element->x);
        self::assertSame(34.75, $element->y);
    }

    #[Test]
    public function it_writes_its_rendered_bytes_to_a_pdf_output_by_default(): void
    {
        $element = new class () extends ContentInstruction {
            public function render(): string
            {
                return 'dummy';
            }
        };
        $output = new StringPdfOutput();

        $element->write($output);

        self::assertSame('dummy', $output->contents());
    }
}
