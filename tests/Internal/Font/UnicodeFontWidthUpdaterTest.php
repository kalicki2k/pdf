<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use Kalle\Pdf\Internal\Font\CidFont;
use Kalle\Pdf\Internal\Font\CidToGidMap;
use Kalle\Pdf\Internal\Font\FontDescriptor;
use Kalle\Pdf\Internal\Font\FontFileStream;
use Kalle\Pdf\Internal\Font\OpenTypeFontParser;
use Kalle\Pdf\Internal\Font\ToUnicodeCMap;
use Kalle\Pdf\Internal\Font\UnicodeFont;
use Kalle\Pdf\Internal\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Internal\Font\UnicodeGlyphMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnicodeFontWidthUpdaterTest extends TestCase
{
    #[Test]
    public function it_updates_widths_for_embedded_unicode_fonts(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');
        $fontData = file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf');

        if ($fontData === false) {
            self::fail('Unable to read assets/fonts/NotoSansCJKsc-Regular.otf.');
        }

        $fontParser = new OpenTypeFontParser($fontData);
        $font = new UnicodeFont(
            12,
            new CidFont(
                13,
                'NotoSansCJKsc-Regular',
                fontDescriptor: new FontDescriptor(
                    14,
                    'NotoSansCJKsc-Regular',
                    new FontFileStream(15, $fontData, 'FontFile3', 'OpenType'),
                ),
                cidToGidMap: new CidToGidMap(16, $glyphMap, $fontParser),
            ),
            new ToUnicodeCMap(17, $glyphMap),
            $glyphMap,
        );

        (new UnicodeFontWidthUpdater())->update($font);

        self::assertStringContainsString('/W [1 [1000] 2 [1000]]', $font->descendantFont->render());
    }

    #[Test]
    public function it_ignores_fonts_without_embedded_metrics(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢');
        $font = new UnicodeFont(
            12,
            new CidFont(13, 'NotoSansCJKsc-Regular'),
            new ToUnicodeCMap(14, $glyphMap),
            $glyphMap,
        );

        (new UnicodeFontWidthUpdater())->update($font);

        self::assertStringNotContainsString('/W [', $font->descendantFont->render());
    }
}
