<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\StringValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StringValueTest extends TestCase
{
    #[Test]
    public function it_wraps_the_escaped_string_in_parentheses(): void
    {
        $value = new StringValue("\\(Line 1)\n\t" . chr(8) . "\f");

        self::assertSame('(\\\\\\(Line 1\\)\\n\\t\\b\\f)', $value->render());
    }
}
