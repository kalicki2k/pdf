<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\BooleanValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BooleanValueTest extends TestCase
{
    #[Test]
    public function it_renders_true(): void
    {
        self::assertSame('true', new BooleanValue(true)->render());
    }

    #[Test]
    public function it_renders_false(): void
    {
        self::assertSame('false', new BooleanValue(false)->render());
    }
}
