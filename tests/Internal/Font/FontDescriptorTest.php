<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use Kalle\Pdf\Font\FontDescriptor;
use Kalle\Pdf\Font\FontFileStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FontDescriptorTest extends TestCase
{
    #[Test]
    public function it_renders_a_font_descriptor_with_a_font_file_reference(): void
    {
        $fontFile = new FontFileStream(30, 'FONTDATA');
        $descriptor = new FontDescriptor(31, 'NotoSans-Regular', $fontFile);

        self::assertSame(
            "31 0 obj\n"
            . "<< /Type /FontDescriptor /FontName /NotoSans-Regular /Flags 4 /FontBBox [0 -200 1000 900] /ItalicAngle 0 /Ascent 800 /Descent -200 /CapHeight 700 /StemV 80 /FontFile2 30 0 R >>\n"
            . "endobj\n",
            $descriptor->render(),
        );
    }

    #[Test]
    public function it_uses_fontfile3_for_opentype_streams(): void
    {
        $fontFile = new FontFileStream(32, 'OTFDATA', 'FontFile3', 'OpenType');
        $descriptor = new FontDescriptor(33, 'NotoSansCJK-Regular', $fontFile);

        self::assertStringContainsString('/FontFile3 32 0 R', $descriptor->render());
    }
}
