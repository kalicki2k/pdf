<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Support\ResolvedBorderSide;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
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
