<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\TextBlockBuilder;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class TextBlockBuilderTest extends TestCase
{
    public function testItBuildsABasicTextBlock(): void
    {
        $block = (new TextBlockBuilder())->build(
            encodedText: 'Hello',
            options: new TextOptions(fontSize: 18),
            x: 72.0,
            y: 720.0,
            fontAlias: 'F1',
            font: StandardFontDefinition::from(StandardFont::HELVETICA),
        );

        self::assertSame("BT\n/F1 18 Tf\n72 720 Td\n(Hello) Tj\nET", $block);
    }

    public function testItBuildsKerningAwareHexTextBlocks(): void
    {
        $block = (new TextBlockBuilder())->build(
            encodedText: 'AV',
            options: new TextOptions(fontSize: 18),
            x: 72.0,
            y: 720.0,
            fontAlias: 'F1',
            font: StandardFontDefinition::from(StandardFont::HELVETICA),
            glyphNames: ['A', 'V'],
            useHexString: true,
        );

        self::assertSame("BT\n/F1 18 Tf\n72 720 Td\n[<41> 71 <56>] TJ\nET", $block);
    }

    public function testItBuildsColorOperatorsIntoTextBlocks(): void
    {
        $block = (new TextBlockBuilder())->build(
            encodedText: 'Hello',
            options: new TextOptions(
                fontSize: 18,
                color: Color::gray(0.5),
            ),
            x: 56.693,
            y: 785.197,
            fontAlias: 'F1',
            font: StandardFontDefinition::from(StandardFont::HELVETICA),
        );

        self::assertSame("BT\n0.5 g\n/F1 18 Tf\n56.693 785.197 Td\n(Hello) Tj\nET", $block);
    }
}
