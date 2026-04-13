<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\StandardFontCoreKerning;
use PHPUnit\Framework\TestCase;

final class StandardFontCoreKerningTest extends TestCase
{
    public function testItExposesAfmKerningPairsForCoreTextFonts(): void
    {
        self::assertGreaterThan(3000, array_sum(array_map(count(...), StandardFontCoreKerning::PAIRS['Helvetica'])));
        self::assertGreaterThan(3000, array_sum(array_map(count(...), StandardFontCoreKerning::PAIRS['Times-Roman'])));
        self::assertSame([], StandardFontCoreKerning::PAIRS['Courier']);
    }

    public function testItReturnsKnownKerningValues(): void
    {
        self::assertSame(-71, StandardFontCoreKerning::value('Helvetica', 'A', 'V'));
        self::assertSame(-87, StandardFontCoreKerning::value('Times-Roman', 'T', 'o'));
        self::assertSame(0, StandardFontCoreKerning::value('Courier', 'A', 'V'));
    }
}
