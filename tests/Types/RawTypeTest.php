<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\RawType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RawTypeTest extends TestCase
{
    #[Test]
    public function it_returns_the_raw_string_unchanged(): void
    {
        self::assertSame('BT /F1 12 Tf ET', new RawType('BT /F1 12 Tf ET')->render());
    }
}
