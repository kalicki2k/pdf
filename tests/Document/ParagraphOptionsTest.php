<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\TextOverflow;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\Style\Opacity;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Text\ParagraphOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParagraphOptionsTest extends TestCase
{
    #[Test]
    public function it_stores_explicit_paragraph_options(): void
    {
        $options = new ParagraphOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: 14.0,
            spacingAfter: 8.0,
            bottomMargin: 6.0,
            color: Color::gray(0.4),
            opacity: Opacity::fill(0.5),
            align: HorizontalAlign::JUSTIFY,
            maxLines: 3,
            overflow: TextOverflow::ELLIPSIS,
        );

        self::assertSame(StructureTag::Paragraph, $options->structureTag);
        self::assertSame(14.0, $options->lineHeight);
        self::assertSame(8.0, $options->spacingAfter);
        self::assertSame(6.0, $options->bottomMargin);
        self::assertSame('0.4 g', $options->color?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.5 >>', $options->opacity?->renderExtGStateDictionary());
        self::assertSame(HorizontalAlign::JUSTIFY, $options->align);
        self::assertSame(3, $options->maxLines);
        self::assertSame(TextOverflow::ELLIPSIS, $options->overflow);
    }

    #[Test]
    public function it_uses_default_paragraph_options(): void
    {
        $options = new ParagraphOptions();

        self::assertNull($options->structureTag);
        self::assertNull($options->lineHeight);
        self::assertNull($options->spacingAfter);
        self::assertNull($options->bottomMargin);
        self::assertNull($options->color);
        self::assertNull($options->opacity);
        self::assertSame(HorizontalAlign::LEFT, $options->align);
        self::assertNull($options->maxLines);
        self::assertSame(TextOverflow::CLIP, $options->overflow);
    }
}
