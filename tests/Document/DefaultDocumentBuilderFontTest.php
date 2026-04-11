<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderFontTest extends TestCase
{
    public function testItAppliesAfmKerningForWesternCoreText(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('AV', new TextOptions(fontName: StandardFont::HELVETICA->value))
            ->build();

        self::assertSame(
            "BT\n/F1 18 Tf\n0 823.89 Td\n[<41> 71 <56>] TJ\nET",
            $document->pages[0]->contents,
        );
    }

    public function testItRejectsNonStandardFonts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'NotoSans-Regular' is not a valid PDF standard font.");

        DefaultDocumentBuilder::make()
            ->text('Hello', new TextOptions(fontName: 'NotoSans-Regular'))
            ->build();
    }

    public function testItEncodesWinAnsiTextForStandardFonts(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('ÄÖÜäöüß€', new TextOptions(fontName: StandardFont::HELVETICA->value))
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a30203832332e38392054640a28c4d6dce4f6fcdf802920546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
    }

    public function testItEncodesWesternTextForPdf10StandardEncoding(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdf10())
            ->text('ÄÖÜäöüß§£¥')
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a30203832332e38392054640a288085868a9a9fa7a4a3b42920546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
    }

    public function testItRejectsUnsupportedTextForPdf10StandardEncoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'StandardEncoding'.");

        DefaultDocumentBuilder::make()
            ->profile(Profile::pdf10())
            ->text('€')
            ->build();
    }

    public function testItEncodesMappedSymbolText(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('αβγΩ', new TextOptions(fontName: StandardFont::SYMBOL->value))
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a30203832332e38392054640a28616267572920546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
    }

    public function testItEncodesMappedZapfDingbatsText(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('✓✔✕✖', new TextOptions(fontName: StandardFont::ZAPF_DINGBATS->value))
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a30203832332e38392054640a28333435362920546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
    }

    public function testItEncodesIsoLatin1TextWhenExplicitlyRequested(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdf10())
            ->text('ÄÖÜäöüß', new TextOptions(
                fontName: StandardFont::HELVETICA->value,
                fontEncoding: StandardFontEncoding::ISO_LATIN_1,
            ))
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a30203832332e38392054640a28c4d6dce4f6fcdf2920546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
        self::assertEquals(
            ['F1' => new PageFont(StandardFont::HELVETICA->value, StandardFontEncoding::ISO_LATIN_1)],
            $document->pages[0]->fontResources,
        );
    }

    public function testItBuildsTextFromExplicitSymbolGlyphNames(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->glyphs(StandardFontGlyphRun::fromGlyphNames(StandardFont::SYMBOL, [
                'registerserif',
                'copyrightserif',
                'trademarkserif',
            ]))
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a30203832332e38392054640a28d2d3d42920546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
    }

    public function testItBuildsTextFromExplicitZapfDingbatsGlyphCodes(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->glyphs(
                StandardFontGlyphRun::fromGlyphCodes(StandardFont::ZAPF_DINGBATS, [0x21, 0x22, 0x23]),
                new TextOptions(fontName: StandardFont::ZAPF_DINGBATS->value),
            )
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a30203832332e38392054640a3c3231323232333e20546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
    }

    public function testItBuildsTextFromExplicitCoreFontGlyphNames(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->glyphs(StandardFontGlyphRun::fromGlyphNames(StandardFont::HELVETICA, [
                'A',
                'Euro',
                'Aogonek',
            ]))
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a30203832332e38392054640a3c3431383038313e20546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
        self::assertEquals(
            ['F1' => new PageFont(StandardFont::HELVETICA->value, StandardFontEncoding::WIN_ANSI, [128 => 'Euro', 129 => 'Aogonek'])],
            $document->pages[0]->fontResources,
        );
    }

    public function testItRejectsUnsupportedSymbolText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'SymbolEncoding'.");

        DefaultDocumentBuilder::make()
            ->text('Hello', new TextOptions(fontName: StandardFont::SYMBOL->value))
            ->build();
    }

    public function testItRejectsUnsupportedZapfDingbatsText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'ZapfDingbatsEncoding'.");

        DefaultDocumentBuilder::make()
            ->text('Hello', new TextOptions(fontName: StandardFont::ZAPF_DINGBATS->value))
            ->build();
    }
}
