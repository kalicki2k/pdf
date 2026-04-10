<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Text\Input;

use Kalle\Pdf\Internal\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\TaggedPdf\StructureTag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextOptionsTest extends TestCase
{
    #[Test]
    public function it_stores_explicit_text_options(): void
    {
        $link = LinkTarget::externalUrl('https://example.com');
        $options = new TextOptions(
            structureTag: StructureTag::Paragraph,
            color: Color::gray(0.4),
            opacity: Opacity::fill(0.5),
            underline: true,
            strikethrough: true,
            link: $link,
        );

        self::assertSame(StructureTag::Paragraph, $options->structureTag);
        self::assertSame('0.4 g', $options->color?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.5 >>', $options->opacity?->renderExtGStateDictionary());
        self::assertTrue($options->underline);
        self::assertTrue($options->strikethrough);
        self::assertSame($link, $options->link);
    }

    #[Test]
    public function it_uses_default_text_options(): void
    {
        $options = new TextOptions();

        self::assertNull($options->structureTag);
        self::assertNull($options->color);
        self::assertNull($options->opacity);
        self::assertFalse($options->underline);
        self::assertFalse($options->strikethrough);
        self::assertNull($options->link);
    }
}
