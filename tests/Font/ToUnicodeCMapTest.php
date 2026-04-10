<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\ToUnicodeCMap;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToUnicodeCMapTest extends TestCase
{
    #[Test]
    public function it_renders_a_to_unicode_cmap_stream_from_the_glyph_map(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');

        $cmap = new ToUnicodeCMap(40, $glyphMap);
        $rendered = $cmap->render();

        self::assertStringContainsString('/CIDInit /ProcSet findresource begin', $rendered);
        self::assertStringContainsString('2 beginbfchar', $rendered);
        self::assertStringContainsString('<0001> <6F22>', $rendered);
        self::assertStringContainsString('<0002> <5B57>', $rendered);
        self::assertStringContainsString('endcmap', $rendered);
    }

    #[Test]
    public function it_writes_a_to_unicode_cmap_stream_via_the_output_path(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');
        $cmap = new ToUnicodeCMap(40, $glyphMap);
        $output = new StringPdfOutput();

        $cmap->write($output);

        self::assertSame($cmap->render(), $output->contents());
    }

    #[Test]
    public function it_writes_an_encrypted_to_unicode_cmap_stream_consistently(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');
        $cmap = new ToUnicodeCMap(40, $glyphMap);
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $cmap->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($cmap->render(), 40),
            $output->contents(),
        );
    }
}
