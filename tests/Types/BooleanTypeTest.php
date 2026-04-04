<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\BooleanType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BooleanTypeTest extends TestCase
{
    #[Test]
    public function it_renders_true(): void
    {
        self::assertSame('true', new BooleanType(true)->render());
    }

    #[Test]
    public function it_renders_false(): void
    {
        self::assertSame('false', new BooleanType(false)->render());
    }
}
