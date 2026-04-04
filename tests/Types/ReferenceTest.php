<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\Reference;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReferenceTest extends TestCase
{
    #[Test]
    public function it_renders_the_object_reference_syntax(): void
    {
        $object = new class (15) extends IndirectObject {
            public function render(): string
            {
                return 'dummy';
            }
        };

        self::assertSame('15 0 R', new Reference($object)->render());
    }
}
