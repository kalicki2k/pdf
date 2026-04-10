<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Binary;

use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Document\Attachment\EmbeddedFileStream;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmbeddedFileStreamTest extends TestCase
{
    #[Test]
    public function it_renders_an_embedded_file_stream(): void
    {
        $stream = new EmbeddedFileStream(7, 'hello', 'text/plain');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /EmbeddedFile /Length 5 /Params << /Size 5 >> /Subtype /text#2Fplain >>\n"
            . "stream\n"
            . "hello\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );
    }

    #[Test]
    public function it_renders_an_embedded_file_stream_from_binary_data(): void
    {
        $stream = new EmbeddedFileStream(7, BinaryData::fromString('hello'), 'text/plain');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /EmbeddedFile /Length 5 /Params << /Size 5 >> /Subtype /text#2Fplain >>\n"
            . "stream\n"
            . "hello\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );
    }

    #[Test]
    public function it_writes_an_embedded_file_stream_to_a_pdf_output(): void
    {
        $stream = new EmbeddedFileStream(7, BinaryData::fromString('hello'), 'text/plain');
        $output = new StringPdfOutput();

        $stream->write($output);

        self::assertSame($stream->render(), $output->contents());
    }

    #[Test]
    public function it_writes_an_encrypted_embedded_file_stream_consistently(): void
    {
        $stream = new EmbeddedFileStream(7, BinaryData::fromString('hello'), 'text/plain');
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
