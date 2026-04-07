<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Layout\HorizontalAlign;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HorizontalAlignTest extends TestCase
{
    #[Test]
    public function it_exposes_the_expected_alignment_values(): void
    {
        self::assertSame('left', HorizontalAlign::LEFT->value);
        self::assertSame('center', HorizontalAlign::CENTER->value);
        self::assertSame('right', HorizontalAlign::RIGHT->value);
        self::assertSame('justify', HorizontalAlign::JUSTIFY->value);
    }
}
