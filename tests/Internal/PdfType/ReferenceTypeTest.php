<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\PdfType;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\PdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReferenceTypeTest extends TestCase
{
    #[Test]
    public function it_renders_the_object_reference_syntax(): void
    {
        $object = new class (15) extends IndirectObject {
            protected function writeObject(PdfOutput $output): void
            {
                $output->write('dummy');
            }
        };

        self::assertSame('15 0 R', new ReferenceType($object)->render());
    }
}
