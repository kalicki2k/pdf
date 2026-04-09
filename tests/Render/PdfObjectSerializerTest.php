<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfObjectSerializer;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfObjectSerializerTest extends TestCase
{
    #[Test]
    public function it_writes_objects_and_returns_their_offsets(): void
    {
        $serializer = new PdfObjectSerializer();
        $output = new StringPdfOutput();
        $firstObject = new class (1) extends IndirectObject {
            public function render(): string
            {
                return "1 0 obj\nalpha\nendobj\n";
            }
        };
        $secondObject = new class (3) extends IndirectObject {
            public function render(): string
            {
                return "3 0 obj\nbeta\nendobj\n";
            }
        };

        $offsets = $serializer->writeObjects([$firstObject, $secondObject], $output);
        $contents = $output->contents();

        self::assertSame(0, $offsets[1]);
        self::assertSame(strpos($contents, "3 0 obj\n"), $offsets[3]);
        self::assertStringContainsString("1 0 obj\nalpha\nendobj\n3 0 obj\nbeta\nendobj\n", $contents);
    }
}
