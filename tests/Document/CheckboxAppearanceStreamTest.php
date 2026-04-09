<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Feature\Form\CheckboxAppearanceStream;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckboxAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_checked_checkbox_appearance_stream(): void
    {
        $stream = new CheckboxAppearanceStream(7, 12, 12, true);

        self::assertStringContainsString('/Subtype /Form', $stream->render());
        self::assertStringContainsString('0 0 12 12 re', $stream->render());
        self::assertStringContainsString("S\nendstream", $stream->render());
    }

    #[Test]
    public function it_writes_an_encrypted_checkbox_appearance_stream_consistently(): void
    {
        $stream = new CheckboxAppearanceStream(7, 12, 12, true);
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $stream->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($stream->render(), 7),
            $output->contents(),
        );
    }
}
