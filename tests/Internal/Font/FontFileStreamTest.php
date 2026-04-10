<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Font\FontFileStream;
use Kalle\Pdf\Internal\Font\OpenTypeFontParser;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Render\StringPdfOutput;
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
    public function it_renders_a_font_stream_from_binary_data(): void
    {
        $stream = new FontFileStream(20, BinaryData::fromString('FONTDATA'));

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

    #[Test]
    public function it_rejects_unreadable_font_files(): void
    {
        $missingPath = sys_get_temp_dir() . '/missing-font-' . uniqid('', true) . '.ttf';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unable to read font file '$missingPath'.");

        FontFileStream::fromPath(24, $missingPath);
    }

    #[Test]
    public function it_reuses_the_same_font_parser_instance(): void
    {
        $stream = FontFileStream::fromPath(25, 'assets/fonts/NotoSans-Regular.ttf');

        $firstParser = $stream->parser();
        $secondParser = $stream->parser();

        self::assertInstanceOf(OpenTypeFontParser::class, $firstParser);
        self::assertSame($firstParser, $secondParser);
        self::assertGreaterThan(0, $firstParser->getUnitsPerEm());
    }

    #[Test]
    public function it_writes_a_font_stream_to_a_pdf_output(): void
    {
        $stream = new FontFileStream(20, BinaryData::fromString('FONTDATA'));
        $output = new StringPdfOutput();

        $stream->write($output);

        self::assertSame($stream->render(), $output->contents());
    }

    #[Test]
    public function it_writes_an_encrypted_font_stream_consistently(): void
    {
        $stream = new FontFileStream(20, BinaryData::fromString('FONTDATA'));
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $stream->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($stream->render(), 20),
            $output->contents(),
        );
    }
}
