<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

require_once __DIR__ . '/Support/StandardObjectEncryptorOpenSslStub.php';

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;

use Kalle\Pdf\Encryption\Profile\EncryptionProfile;

use function Kalle\Pdf\Encryption\setStandardObjectEncryptorOpenSslShouldFail;

use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StandardObjectEncryptorTest extends TestCase
{
    protected function tearDown(): void
    {
        setStandardObjectEncryptorOpenSslShouldFail(false);
    }

    #[Test]
    public function it_leaves_objects_unchanged_when_the_profile_does_not_support_object_encryption(): void
    {
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::AUTO, 0, 0, 0),
            new StandardSecurityHandlerData('', '', 'secret', -4),
        );

        self::assertFalse($encryptor->supportsObjectEncryption());
        self::assertSame('plain-text', $encryptor->encryptString(1, 'plain-text'));
        self::assertSame("<< /Length 4 >>\nstream\ndata\nendstream", $encryptor->encryptStreamObject("<< /Length 4 >>\nstream\ndata\nendstream", 1));
    }

    #[Test]
    public function it_encrypts_strings_with_rc4_for_supported_profiles(): void
    {
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );

        $encrypted = $encryptor->encryptString(7, 'plain-text');

        self::assertNotSame('plain-text', $encrypted);
        self::assertSame(strlen('plain-text'), strlen($encrypted));
    }

    #[Test]
    public function it_encrypts_stream_objects_and_updates_their_length_for_aes_profiles(): void
    {
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::AES_128, 128, 4, 4),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );

        $encrypted = $encryptor->encryptStreamObject("<< /Length 4 >>\nstream\ndata\nendstream", 9);

        self::assertStringContainsString("stream\n", $encrypted);
        self::assertStringContainsString("\nendstream", $encrypted);
        self::assertDoesNotMatchRegularExpression('/\/Length 4\b/', $encrypted);
    }

    #[Test]
    public function it_rejects_aes_object_encryption_when_openssl_encryption_fails(): void
    {
        setStandardObjectEncryptorOpenSslShouldFail(true);

        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::AES_128, 128, 4, 4),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to encrypt PDF object payload with aes-128-cbc.');

        $encryptor->encryptString(9, 'data');
    }

    #[Test]
    public function it_returns_stream_objects_unchanged_when_no_stream_marker_is_present(): void
    {
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_40, 40, 1, 2),
            new StandardSecurityHandlerData('', '', '12345', -4),
        );

        self::assertSame('<< /Type /Example >>', $encryptor->encryptStreamObject('<< /Type /Example >>', 3));
    }

    #[Test]
    public function it_rejects_stream_objects_without_an_endstream_marker(): void
    {
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_40, 40, 1, 2),
            new StandardSecurityHandlerData('', '', '12345', -4),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to locate stream end marker in rendered object.');

        $encryptor->encryptStreamObject("<< /Length 4 >>\nstream\ndata", 3);
    }
}
