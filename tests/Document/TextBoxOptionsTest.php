<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Internal\Layout\Geometry\Insets;
use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\TextOverflow;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\Style\Opacity;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Text\TextBoxOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextBoxOptionsTest extends TestCase
{
    #[Test]
    public function it_stores_explicit_text_box_options(): void
    {
        $padding = new Insets(top: 1, right: 2, bottom: 3, left: 4);
        $options = new TextBoxOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: 14.0,
            color: Color::gray(0.4),
            opacity: Opacity::fill(0.5),
            align: HorizontalAlign::CENTER,
            verticalAlign: VerticalAlign::MIDDLE,
            maxLines: 3,
            overflow: TextOverflow::ELLIPSIS,
            padding: $padding,
        );

        self::assertSame(StructureTag::Paragraph, $options->structureTag);
        self::assertSame(14.0, $options->lineHeight);
        self::assertSame('0.4 g', $options->color?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.5 >>', $options->opacity?->renderExtGStateDictionary());
        self::assertSame(HorizontalAlign::CENTER, $options->align);
        self::assertSame(VerticalAlign::MIDDLE, $options->verticalAlign);
        self::assertSame(3, $options->maxLines);
        self::assertSame(TextOverflow::ELLIPSIS, $options->overflow);
        self::assertSame($padding, $options->padding);
    }

    #[Test]
    public function it_uses_default_text_box_options(): void
    {
        $options = new TextBoxOptions();

        self::assertNull($options->structureTag);
        self::assertNull($options->lineHeight);
        self::assertNull($options->color);
        self::assertNull($options->opacity);
        self::assertSame(HorizontalAlign::LEFT, $options->align);
        self::assertSame(VerticalAlign::TOP, $options->verticalAlign);
        self::assertNull($options->maxLines);
        self::assertSame(TextOverflow::CLIP, $options->overflow);
        self::assertSame(0.0, $options->padding->top);
        self::assertSame(0.0, $options->padding->right);
        self::assertSame(0.0, $options->padding->bottom);
        self::assertSame(0.0, $options->padding->left);
    }
}
