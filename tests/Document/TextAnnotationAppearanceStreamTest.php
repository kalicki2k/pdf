<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextAnnotationAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_text_annotation_appearance_stream(): void
    {
        $stream = new TextAnnotationAppearanceStream(7, 16, 18);

        $rendered = $stream->render();

        self::assertStringContainsString('7 0 obj', $rendered);
        self::assertStringContainsString('/Subtype /Form', $rendered);
        self::assertStringContainsString('/BBox [0 0 16 18]', $rendered);
        self::assertStringContainsString('0 0 16 18 re', $rendered);
        self::assertStringContainsString("B\nendstream", $rendered);
    }

    #[Test]
    public function it_writes_an_encrypted_text_annotation_appearance_stream_consistently(): void
    {
        $stream = new TextAnnotationAppearanceStream(7, 16, 18);
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
