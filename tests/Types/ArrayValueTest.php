<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\BooleanValue;
use Kalle\Pdf\Types\Name;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayValueTest extends TestCase
{
    #[Test]
    public function it_renders_scalar_and_value_entries_in_order(): void
    {
        $value = new ArrayValue([
            new Name('Type'),
            12,
            3.5,
            new BooleanValue(true),
        ]);

        self::assertSame('[/Type 12 3.5 true]', $value->render());
    }
}
