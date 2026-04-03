<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\RawValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RawValueTest extends TestCase
{
    #[Test]
    public function it_returns_the_raw_string_unchanged(): void
    {
        self::assertSame('BT /F1 12 Tf ET', new RawValue('BT /F1 12 Tf ET')->render());
    }
}
