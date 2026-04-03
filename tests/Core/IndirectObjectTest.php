<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use Kalle\Pdf\Core\IndirectObject;
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
}
