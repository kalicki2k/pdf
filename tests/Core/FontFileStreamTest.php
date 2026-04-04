<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use InvalidArgumentException;
use Kalle\Pdf\Core\FontFileStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FontFileStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_font_file_stream_object(): void
    {
        $stream = new FontFileStream(20, 'FONTDATA');

        self::assertSame(
            "20 0 obj\n"
            . "<< /Length 8 /Length1 8 >>\n"
            . "stream\n"
            . "FONTDATA\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );
    }

    #[Test]
    public function it_marks_opentype_streams_with_their_subtype(): void
    {
        $stream = new FontFileStream(21, 'OTFDATA', 'FontFile3', 'OpenType');

        self::assertSame(
            "21 0 obj\n"
            . "<< /Length 7 /Length1 7 /Subtype /OpenType >>\n"
            . "stream\n"
            . "OTFDATA\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );
    }

    #[Test]
    public function it_creates_a_font_stream_from_a_supported_file_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'font_');
        self::assertNotFalse($path);

        $ttfPath = $path . '.ttf';
        rename($path, $ttfPath);
        file_put_contents($ttfPath, 'ABC');

        $stream = FontFileStream::fromPath(22, $ttfPath);

        self::assertSame(
            "22 0 obj\n"
            . "<< /Length 3 /Length1 3 >>\n"
            . "stream\n"
            . "ABC\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );

        unlink($ttfPath);
    }

    #[Test]
    public function it_rejects_unsupported_font_file_extensions(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'font_');
        self::assertNotFalse($path);

        $woffPath = $path . '.woff';
        rename($path, $woffPath);
        file_put_contents($woffPath, 'ABC');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported font file extension 'woff'.");

        try {
            FontFileStream::fromPath(23, $woffPath);
        } finally {
            unlink($woffPath);
        }
    }
}
