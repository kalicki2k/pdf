<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Feature\Form\FormFieldSignatureAppearanceStream;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormFieldSignatureAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_form_field_signature_appearance_stream(): void
    {
        $stream = new FormFieldSignatureAppearanceStream(7, 100, 30);

        $rendered = $stream->render();

        self::assertStringContainsString('7 0 obj', $rendered);
        self::assertStringContainsString('/Subtype /Form', $rendered);
        self::assertStringContainsString('/BBox [0 0 100 30]', $rendered);
        self::assertStringContainsString('0 0 100 30 re', $rendered);
        self::assertStringContainsString('4 8.4 m', $rendered);
        self::assertStringContainsString('96 8.4 l', $rendered);
    }

    #[Test]
    public function it_writes_an_encrypted_signature_appearance_stream_consistently(): void
    {
        $stream = new FormFieldSignatureAppearanceStream(7, 100, 30);
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
