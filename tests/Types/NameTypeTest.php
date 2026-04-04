<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\NameType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NameTypeTest extends TestCase
{
    #[Test]
    public function it_prefixes_the_name_with_a_slash(): void
    {
        self::assertSame('/Catalog', new NameType('Catalog')->render());
    }
}
