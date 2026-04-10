<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\PdfType;

use Kalle\Pdf\PdfType\RawType;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class RawTypeTest extends TestCase
{
    #[Test]
    public function it_returns_the_raw_string_unchanged(): void
    {
        self::assertSame('BT /F1 12 Tf ET', writePdfTypeToString(new RawType('BT /F1 12 Tf ET')));
    }
}
