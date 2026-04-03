<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\Name;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NameTest extends TestCase
{
    #[Test]
    public function it_prefixes_the_name_with_a_slash(): void
    {
        self::assertSame('/Catalog', new Name('Catalog')->render());
    }
}
