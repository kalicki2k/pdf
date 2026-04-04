<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\BooleanType;
use Kalle\Pdf\Types\NameType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayTypeTest extends TestCase
{
    #[Test]
    public function it_renders_scalar_and_value_entries_in_order(): void
    {
        $value = new ArrayType([
            new NameType('Type'),
            12,
            3.5,
            new BooleanType(true),
        ]);

        self::assertSame('[/Type 12 3.5 true]', $value->render());
    }
}
