<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Style;

use InvalidArgumentException;
use Kalle\Pdf\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpacityTest extends TestCase
{
    #[Test]
    public function it_renders_fill_opacity_as_extgstate_dictionary(): void
    {
        $opacity = Opacity::fill(0.5);

        self::assertSame('<< /ca 0.5 >>', $opacity->renderExtGStateDictionary());
    }

    #[Test]
    public function it_renders_stroke_opacity_as_extgstate_dictionary(): void
    {
        $opacity = Opacity::stroke(0.25);

        self::assertSame('<< /CA 0.25 >>', $opacity->renderExtGStateDictionary());
    }

    #[Test]
    public function it_renders_fill_and_stroke_opacity_as_extgstate_dictionary(): void
    {
        $opacity = Opacity::both(0.75);

        self::assertSame('<< /ca 0.75 /CA 0.75 >>', $opacity->renderExtGStateDictionary());
    }

    #[Test]
    public function it_rejects_invalid_fill_opacity_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fill opacity must be between 0.0 and 1.0, got 1.5.');

        Opacity::fill(1.5);
    }

    #[Test]
    public function it_rejects_invalid_stroke_opacity_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stroke opacity must be between 0.0 and 1.0, got -0.1.');

        Opacity::stroke(-0.1);
    }

    #[Test]
    public function it_rejects_invalid_shared_opacity_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Opacity must be between 0.0 and 1.0, got 1.1.');

        Opacity::both(1.1);
    }
}
