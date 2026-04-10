<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Binary;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Binary\BinaryData;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Model\Document\IccProfileStream;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IccProfileStreamTest extends TestCase
{
    #[Test]
    public function it_renders_an_icc_profile_stream(): void
    {
        $stream = new IccProfileStream(11, 'ICC', 3);

        self::assertSame(
            "11 0 obj\n"
            . "<< /N 3 /Length 3 >>\n"
            . "stream\n"
            . "ICC\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );
    }

    #[Test]
    public function it_renders_an_icc_profile_stream_from_binary_data(): void
    {
        $stream = new IccProfileStream(11, BinaryData::fromString('ICC'), 4);

        self::assertSame(
            "11 0 obj\n"
            . "<< /N 4 /Length 3 >>\n"
            . "stream\n"
            . "ICC\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );
    }

    #[Test]
    public function it_reads_an_icc_profile_from_a_file_source_when_rendered(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-icc-profile-');
        self::assertNotFalse($path);
        file_put_contents($path, 'profile-data');

        try {
            $stream = IccProfileStream::fromPath(11, $path, 3);
            file_put_contents($path, 'changed');

            self::assertStringContainsString('/Length 7', $stream->render());
            self::assertStringContainsString("stream\nchanged\n", $stream->render());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_rejects_unreadable_icc_profiles(): void
    {
        $missingPath = sys_get_temp_dir() . '/missing-icc-' . uniqid('', true) . '.icc';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unable to read ICC profile '$missingPath'.");

        IccProfileStream::fromPath(11, $missingPath, 3);
    }

    #[Test]
    public function it_writes_an_icc_profile_stream_to_a_pdf_output(): void
    {
        $stream = new IccProfileStream(11, BinaryData::fromString('ICC'), 4);
        $output = new StringPdfOutput();

        $stream->write($output);

        self::assertSame($stream->render(), $output->contents());
    }

    #[Test]
    public function it_writes_an_encrypted_icc_profile_stream_consistently(): void
    {
        $stream = new IccProfileStream(11, BinaryData::fromString('ICC'), 4);
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $stream->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($stream->render(), 11),
            $output->contents(),
        );
    }
}
