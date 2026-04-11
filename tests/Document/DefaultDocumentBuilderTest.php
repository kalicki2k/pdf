<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderTest extends TestCase
{
    public function testItBuildsADocumentFromConfiguredMetadata(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->subject('Example Subject')
            ->language('de-DE')
            ->creator('Kalle PDF')
            ->creatorTool('pdf2 test suite')
            ->pageSize(PageSize::A5())
            ->text('Hello (PDF) \\ Test', new TextOptions(
                x: Units::mm(20),
                y: Units::mm(250),
                fontSize: 14,
                fontName: 'Times-Roman',
            ))
            ->build();

        self::assertSame(Version::V1_4, $document->version());
        self::assertSame('Example Title', $document->title);
        self::assertSame('Sebastian Kalicki', $document->author);
        self::assertSame('Example Subject', $document->subject);
        self::assertSame('de-DE', $document->language);
        self::assertSame('Kalle PDF', $document->creator);
        self::assertSame('pdf2 test suite', $document->creatorTool);
        self::assertCount(1, $document->pages);
        self::assertSame(PageSize::A5()->width(), $document->pages[0]->size->width());
        self::assertSame(PageSize::A5()->height(), $document->pages[0]->size->height());
        self::assertStringContainsString("BT\n/F1 14 Tf\n56.693 708.661 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString('] TJ' . "\nET", $document->pages[0]->contents);
        self::assertEquals(
            ['F1' => new PageFont('Times-Roman', StandardFontEncoding::WIN_ANSI)],
            $document->pages[0]->fontResources,
        );
    }

    public function testItBuildsMultiplePagesWhenNewPageIsUsed(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Page 1')
            ->newPage(new PageOptions(
                pageSize: PageSize::A5(),
                orientation: PageOrientation::LANDSCAPE,
                margin: Margin::all(24.0),
                backgroundColor: Color::hex('#f5f5f5'),
                label: 'appendix',
                name: 'appendix-a',
            ))
            ->text('Page 2')
            ->build();

        self::assertCount(2, $document->pages);
        self::assertStringContainsString('[<50>', $document->pages[0]->contents);
        self::assertStringContainsString('[<50>', $document->pages[1]->contents);
        self::assertStringContainsString('] TJ', $document->pages[0]->contents);
        self::assertStringContainsString('] TJ', $document->pages[1]->contents);
        self::assertSame(PageSize::A5()->landscape()->width(), $document->pages[1]->size->width());
        self::assertSame(PageSize::A5()->landscape()->height(), $document->pages[1]->size->height());
        self::assertSame(24.0, $document->pages[1]->margin?->top);
        self::assertSame(ColorSpace::RGB, $document->pages[1]->backgroundColor?->space);
        self::assertSame([245 / 255, 245 / 255, 245 / 255], $document->pages[1]->backgroundColor?->components());
        self::assertSame('appendix', $document->pages[1]->label);
        self::assertSame('appendix-a', $document->pages[1]->name);
    }

    public function testItWritesTextColorOperatorsIntoPageContents(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Gray', new TextOptions(
                color: Color::gray(0.5),
            ))
            ->text('CMYK', new TextOptions(
                y: 680.0,
                color: Color::cmyk(0.1, 0.2, 0.3, 0.4),
            ))
            ->build();

        self::assertStringContainsString("BT\n0.5 g\n/F1 18 Tf\n72 720 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n0.1 0.2 0.3 0.4 k\n/F1 18 Tf\n72 680 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString('] TJ', $document->pages[0]->contents);
    }

    public function testItUsesTheConfiguredFirstPageMarginForImplicitTextPlacement(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 785.197 Td\n[", $document->pages[0]->contents);
        self::assertSame(Units::mm(20), $document->pages[0]->margin?->top);
    }

    public function testItAdvancesImplicitTextPlacementBelowThePreviousLine(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->margin(Margin::all(Units::mm(20)))
            ->text('Line 1')
            ->text('Line 2')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 785.197 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 763.597 Td\n[", $document->pages[0]->contents);
    }

    public function testItWrapsTextWithinTheCurrentContentArea(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello world this wraps automatically across multiple lines.')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 538.583 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 516.983 Td\n[", $document->pages[0]->contents);
    }

    public function testItContinuesBelowAllWrappedLinesForTheNextImplicitTextCall(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello world this wraps automatically across multiple lines.')
            ->text('After wrap')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 495.383 Td\n[", $document->pages[0]->contents);
    }

    public function testItAppliesAfmKerningForWesternCoreText(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('AV', new TextOptions(fontName: StandardFont::HELVETICA->value))
            ->build();

        self::assertSame(
            "BT\n/F1 18 Tf\n72 720 Td\n[<41> 71 <56>] TJ\nET",
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

    public function testItBuildsADocumentWithAnExplicitProfile(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::standard(Version::V1_7))
            ->build();

        self::assertSame(Version::V1_7, $document->version());
        self::assertSame(Version::V1_7, $document->profile->version());
    }

    public function testItEncodesWinAnsiTextForStandardFonts(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('ÄÖÜäöüß€', new TextOptions(fontName: StandardFont::HELVETICA->value))
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a3732203732302054640a28c4d6dce4f6fcdf802920546a0a4554',
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
            '42540a2f46312031382054660a3732203732302054640a288085868a9a9fa7a4a3b42920546a0a4554',
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
            '42540a2f46312031382054660a3732203732302054640a28616267572920546a0a4554',
            bin2hex($document->pages[0]->contents),
        );
    }

    public function testItEncodesMappedZapfDingbatsText(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('✓✔✕✖', new TextOptions(fontName: StandardFont::ZAPF_DINGBATS->value))
            ->build();

        self::assertSame(
            '42540a2f46312031382054660a3732203732302054640a28333435362920546a0a4554',
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
            '42540a2f46312031382054660a3732203732302054640a28c4d6dce4f6fcdf2920546a0a4554',
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
            '42540a2f46312031382054660a3732203732302054640a28d2d3d42920546a0a4554',
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
            '42540a2f46312031382054660a3732203732302054640a3c3231323232333e20546a0a4554',
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
            '42540a2f46312031382054660a3732203732302054640a3c3431383038313e20546a0a4554',
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
