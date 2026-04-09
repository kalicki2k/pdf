<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Form\FormFieldListBoxAppearanceStream;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormFieldListBoxAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_list_box_options_with_selection_highlights(): void
    {
        $font = new StandardFont(9, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);
        $stream = new FormFieldListBoxAppearanceStream(
            7,
            80,
            40,
            $font,
            'F1',
            12,
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            ['forms'],
        );

        $rendered = $stream->render();

        self::assertStringContainsString('0.219608 0.458824 0.843137 rg', $rendered);
        self::assertStringContainsString('1 8.7 78 14.4 re', $rendered);
        self::assertStringContainsString('1 0 0 1 2.5 25.5 Tm', $rendered);
        self::assertStringContainsString('(PDF) Tj', $rendered);
        self::assertStringContainsString('1 0 0 1 2.5 11.1 Tm', $rendered);
        self::assertStringContainsString('(Forms) Tj', $rendered);
        self::assertStringContainsString('1 g', $rendered);
        self::assertStringNotContainsString('(Tables) Tj', $rendered);
    }

    #[Test]
    public function it_writes_an_encrypted_list_box_appearance_stream_consistently(): void
    {
        $font = new StandardFont(9, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);
        $stream = new FormFieldListBoxAppearanceStream(
            7,
            80,
            40,
            $font,
            'F1',
            12,
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            ['forms'],
        );
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
