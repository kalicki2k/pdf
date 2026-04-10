<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Layout\Table\Support\ResolvedBorderSide;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResolvedBorderSideTest extends TestCase
{
    #[Test]
    public function it_stores_resolved_border_side_values(): void
    {
        $side = new ResolvedBorderSide(
            1.5,
            Color::rgb(255, 0, 0),
            Opacity::both(0.4),
        );

        self::assertSame(1.5, $side->width);
        self::assertSame('1 0 0 RG', $side->color?->renderStrokingOperator());
        self::assertSame('<< /ca 0.4 /CA 0.4 >>', $side->opacity?->renderExtGStateDictionary());
    }
}
