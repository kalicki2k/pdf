<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\PdfType;

use Kalle\Pdf\PdfType\BooleanType;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class BooleanTypeTest extends TestCase
{
    #[Test]
    public function it_renders_true(): void
    {
        self::assertSame('true', writePdfTypeToString(new BooleanType(true)));
    }

    #[Test]
    public function it_renders_false(): void
    {
        self::assertSame('false', writePdfTypeToString(new BooleanType(false)));
    }
}
