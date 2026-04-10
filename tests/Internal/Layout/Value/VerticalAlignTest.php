<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Value;

use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VerticalAlignTest extends TestCase
{
    #[Test]
    public function it_exposes_the_expected_alignment_values(): void
    {
        self::assertSame('top', VerticalAlign::TOP->value);
        self::assertSame('middle', VerticalAlign::MIDDLE->value);
        self::assertSame('bottom', VerticalAlign::BOTTOM->value);
    }
}
