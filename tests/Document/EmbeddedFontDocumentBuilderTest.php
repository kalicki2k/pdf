<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class EmbeddedFontDocumentBuilderTest extends TestCase
{
    public function testItBuildsTextWithAnEmbeddedTrueTypeFont(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('A', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->isEmbedded());
        self::assertSame('TestFont-Regular', $font->name);
        self::assertStringContainsString('/F1 18 Tf', $document->pages[0]->contents);
        self::assertStringContainsString('(A) Tj', $document->pages[0]->contents);
    }

    public function testItBuildsTextWithAnEmbeddedCffFont(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('A', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalCffOpenTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->isEmbedded());
        self::assertSame('TestCff-Regular', $font->name);
        self::assertFalse($font->usesUnicodeCids());
        self::assertStringContainsString('/F1 18 Tf', $document->pages[0]->contents);
        self::assertStringContainsString('(A) Tj', $document->pages[0]->contents);
    }

    public function testItBuildsUnicodeTextWithAnEmbeddedTrueTypeFont(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
            ))
            ->text('中', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
            ))
            ->text('😀', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->isEmbedded());
        self::assertTrue($font->usesUnicodeCids());
        self::assertSame([0x0416, 0x4E2D, 0x1F600], $font->unicodeCodePoints);
        self::assertStringContainsString('/F1 18 Tf', $document->pages[0]->contents);
        self::assertStringContainsString('<0001> Tj', $document->pages[0]->contents);
        self::assertStringContainsString('<0002> Tj', $document->pages[0]->contents);
        self::assertStringContainsString('<0003> Tj', $document->pages[0]->contents);
    }

    public function testItBuildsUnicodeTextWithAnEmbeddedCffFont(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()),
            ))
            ->text('中', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->isEmbedded());
        self::assertTrue($font->usesUnicodeCids());
        self::assertSame('TestCff-Regular', $font->name);
        self::assertSame([0x0416, 0x4E2D], $font->unicodeCodePoints);
        self::assertStringContainsString('<0001> Tj', $document->pages[0]->contents);
        self::assertStringContainsString('<0002> Tj', $document->pages[0]->contents);
    }

    public function testItBuildsArabicUnicodeTextUsingShapedEmbeddedGlyphIds(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('ببب', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->usesUnicodeCids());
        self::assertSame([5, 7, 6], array_map(
            static fn ($glyph): int => $glyph->glyphId,
            $font->embeddedGlyphs,
        ));
        self::assertStringContainsString('[<0001> 60 <0002> 40 <0003>] TJ', $document->pages[0]->contents);
    }

    public function testItBuildsArabicUnicodeLigaturesUsingShapedEmbeddedGlyphIds(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('لا', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->usesUnicodeCids());
        self::assertSame([8], array_map(
            static fn ($glyph): int => $glyph->glyphId,
            $font->embeddedGlyphs,
        ));
        self::assertSame(['لا'], array_map(
            static fn ($glyph): string => $glyph->unicodeText,
            $font->embeddedGlyphs,
        ));
        self::assertStringContainsString('<0001> Tj', $document->pages[0]->contents);
    }

    public function testItBuildsGeneralLigaLigaturesUsingShapedEmbeddedGlyphIds(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('fi', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalLatinLigaTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->usesUnicodeCids());
        self::assertSame([3], array_map(
            static fn ($glyph): int => $glyph->glyphId,
            $font->embeddedGlyphs,
        ));
        self::assertSame(['fi'], array_map(
            static fn ($glyph): string => $glyph->unicodeText,
            $font->embeddedGlyphs,
        ));
        self::assertStringContainsString('<0001> Tj', $document->pages[0]->contents);
    }

    public function testItBuildsGeneralCaltSubstitutionsUsingShapedEmbeddedGlyphIds(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('fi', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalLatinContextualTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->usesUnicodeCids());
        self::assertSame([3, 2], array_map(
            static fn ($glyph): int => $glyph->glyphId,
            $font->embeddedGlyphs,
        ));
        self::assertSame(['f', 'i'], array_map(
            static fn ($glyph): string => $glyph->unicodeText,
            $font->embeddedGlyphs,
        ));
        self::assertStringContainsString('<00010002> Tj', $document->pages[0]->contents);
    }

    public function testItBuildsGeneralCaltChainingSubstitutionsUsingShapedEmbeddedGlyphIds(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('fi', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalLatinChainingContextualTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->usesUnicodeCids());
        self::assertSame([3, 2], array_map(
            static fn ($glyph): int => $glyph->glyphId,
            $font->embeddedGlyphs,
        ));
        self::assertSame(['f', 'i'], array_map(
            static fn ($glyph): string => $glyph->unicodeText,
            $font->embeddedGlyphs,
        ));
        self::assertStringContainsString('<00010002> Tj', $document->pages[0]->contents);
    }

    public function testItBuildsStackedArabicMarksUsingPositionedFragments(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('بَّ', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->usesUnicodeCids());
        self::assertSame([10, 9, 4], array_map(
            static fn ($glyph): int => $glyph->glyphId,
            $font->embeddedGlyphs,
        ));
        self::assertStringContainsString(' Tm', $document->pages[0]->contents);
        self::assertStringContainsString('<0001> Tj', $document->pages[0]->contents);
        self::assertStringContainsString('<0002> Tj', $document->pages[0]->contents);
        self::assertStringContainsString('<0003> Tj', $document->pages[0]->contents);
    }
}
