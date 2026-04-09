<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Annotation\AnnotationBorderStyle;
use Kalle\Pdf\Feature\Annotation\AnnotationBorderStyleType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnnotationBorderStyleTest extends TestCase
{
    #[Test]
    public function it_creates_a_solid_border_style_via_the_named_constructor(): void
    {
        $style = AnnotationBorderStyle::solid(2.5);

        self::assertSame(2.5, $style->width);
        self::assertSame(AnnotationBorderStyleType::SOLID, $style->style);
        self::assertSame([], $style->dashPattern);
        self::assertSame('<< /W 2.5 /S /S >>', $style->toPdfDictionary()->render());
    }

    #[Test]
    public function it_creates_a_dashed_border_style_with_a_dash_pattern(): void
    {
        $style = AnnotationBorderStyle::dashed(1.5, [2.0, 1.0]);

        self::assertSame(1.5, $style->width);
        self::assertSame(AnnotationBorderStyleType::DASHED, $style->style);
        self::assertSame([2.0, 1.0], $style->dashPattern);
        self::assertSame('<< /W 1.5 /S /D /D [2 1] >>', $style->toPdfDictionary()->render());
    }

    #[Test]
    public function it_omits_the_dash_pattern_for_a_dashed_style_when_no_pattern_is_given(): void
    {
        $style = new AnnotationBorderStyle(1.0, AnnotationBorderStyleType::DASHED);

        self::assertSame('<< /W 1 /S /D >>', $style->toPdfDictionary()->render());
    }

    #[Test]
    public function it_rejects_negative_border_widths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Annotation border width must be zero or greater.');

        new AnnotationBorderStyle(-1.0);
    }

    #[Test]
    public function it_rejects_negative_dash_pattern_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Annotation dash pattern values must be zero or greater.');

        new AnnotationBorderStyle(1.0, AnnotationBorderStyleType::DASHED, [2.0, -1.0]);
    }
}
