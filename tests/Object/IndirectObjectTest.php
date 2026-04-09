<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Object;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndirectObjectTest extends TestCase
{
    #[Test]
    public function it_exposes_the_assigned_object_id(): void
    {
        $object = new class (42) extends IndirectObject {
            public function render(): string
            {
                return 'dummy';
            }
        };

        self::assertSame(42, $object->id);
    }

    #[Test]
    public function it_writes_its_rendered_bytes_to_a_pdf_output_by_default(): void
    {
        $object = new class (42) extends IndirectObject {
            public function render(): string
            {
                return 'dummy';
            }
        };
        $output = new StringPdfOutput();

        $object->write($output);

        self::assertSame('dummy', $output->contents());
    }
}
