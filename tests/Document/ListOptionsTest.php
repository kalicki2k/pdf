<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\Text\ListOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListOptionsTest extends TestCase
{
    #[Test]
    public function it_stores_explicit_list_options(): void
    {
        $options = new ListOptions(
            structureTag: StructureTag::List,
            lineHeight: 14.0,
            spacingAfter: 8.0,
            itemSpacing: 4.0,
            color: Color::gray(0.2),
            opacity: Opacity::fill(0.5),
            markerColor: Color::rgb(255, 0, 0),
            markerIndent: 12.0,
        );

        self::assertSame(StructureTag::List, $options->structureTag);
        self::assertSame(14.0, $options->lineHeight);
        self::assertSame(8.0, $options->spacingAfter);
        self::assertSame(4.0, $options->itemSpacing);
        self::assertSame('0.2 g', $options->color?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.5 >>', $options->opacity?->renderExtGStateDictionary());
        self::assertSame('1 0 0 rg', $options->markerColor?->renderNonStrokingOperator());
        self::assertSame(12.0, $options->markerIndent);
    }

    #[Test]
    public function it_uses_default_list_options(): void
    {
        $options = new ListOptions();

        self::assertNull($options->structureTag);
        self::assertNull($options->lineHeight);
        self::assertNull($options->spacingAfter);
        self::assertNull($options->itemSpacing);
        self::assertNull($options->color);
        self::assertNull($options->opacity);
        self::assertNull($options->markerColor);
        self::assertNull($options->markerIndent);
    }
}
