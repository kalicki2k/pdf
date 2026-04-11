<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Text\ShapedGlyph;
use Kalle\Pdf\Text\ShapedTextRun;
use Kalle\Pdf\Text\SimpleScriptGlyphMapper;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextScript;
use PHPUnit\Framework\TestCase;

final class SimpleScriptGlyphMapperTest extends TestCase
{
    public function testItReturnsGlyphNamesForLatinRuns(): void
    {
        $mapper = new SimpleScriptGlyphMapper();
        $run = new ShapedTextRun(TextDirection::LTR, TextScript::LATIN, [
            new ShapedGlyph('A', 0),
            new ShapedGlyph('V', 1),
        ]);

        $glyphNames = $mapper->glyphNamesForRun(
            $run,
            StandardFontDefinition::from(StandardFont::HELVETICA),
            new TextOptions(fontName: StandardFont::HELVETICA->value),
            1.4,
        );

        self::assertSame(['A', 'V'], $glyphNames);
    }

    public function testItReturnsNoGlyphNamesForHebrewRuns(): void
    {
        $mapper = new SimpleScriptGlyphMapper();
        $run = new ShapedTextRun(TextDirection::RTL, TextScript::HEBREW, [
            new ShapedGlyph('ש', 0),
            new ShapedGlyph('ל', 1),
        ]);

        $glyphNames = $mapper->glyphNamesForRun(
            $run,
            StandardFontDefinition::from(StandardFont::HELVETICA),
            new TextOptions(fontName: StandardFont::HELVETICA->value),
            1.4,
        );

        self::assertSame([], $glyphNames);
    }

    public function testItPreservesGlyphNamesAlreadyAssignedByTheShaper(): void
    {
        $mapper = new SimpleScriptGlyphMapper();
        $run = new ShapedTextRun(TextDirection::RTL, TextScript::ARABIC, [
            new ShapedGlyph('ﺑ', 0, form: 'initial', glyphName: 'arabic.beh.initial'),
            new ShapedGlyph('ﺐ', 1, form: 'final', glyphName: 'arabic.beh.final'),
        ]);

        $glyphNames = $mapper->glyphNamesForRun(
            $run,
            StandardFontDefinition::from(StandardFont::HELVETICA),
            new TextOptions(fontName: StandardFont::HELVETICA->value),
            1.4,
        );

        self::assertSame(['arabic.beh.initial', 'arabic.beh.final'], $glyphNames);
    }
}
