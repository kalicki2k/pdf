<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Layout\BulletType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BulletTypeTest extends TestCase
{
    #[Test]
    public function it_exposes_the_expected_bullet_symbols(): void
    {
        self::assertSame("\u{2022}", BulletType::DISC->value);
        self::assertSame('-', BulletType::DASH->value);
        self::assertSame("\u{25E6}", BulletType::CIRCLE->value);
        self::assertSame("\u{25AA}", BulletType::SQUARE->value);
        self::assertSame("\u{2192}", BulletType::ARROW->value);
    }
}
