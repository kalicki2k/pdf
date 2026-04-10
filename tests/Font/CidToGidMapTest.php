<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\CidToGidMap;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CidToGidMapTest extends TestCase
{
    #[Test]
    public function it_renders_a_cid_to_gid_map_stream(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');
        $parser = new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf'));
        $map = new CidToGidMap(50, $glyphMap, $parser);

        $rendered = $map->render();

        self::assertStringContainsString("50 0 obj\n", $rendered);
        self::assertStringContainsString("stream\n", $rendered);
        self::assertStringContainsString("endstream\n", $rendered);
    }

    #[Test]
    public function it_writes_a_cid_to_gid_map_stream_via_the_output_path(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');
        $parser = new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf'));
        $map = new CidToGidMap(50, $glyphMap, $parser);
        $output = new StringPdfOutput();

        $map->write($output);

        self::assertSame($map->render(), $output->contents());
    }

    #[Test]
    public function it_writes_an_encrypted_cid_to_gid_map_stream_consistently(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');
        $parser = new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf'));
        $map = new CidToGidMap(50, $glyphMap, $parser);
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $map->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($map->render(), 50),
            $output->contents(),
        );
    }
}
