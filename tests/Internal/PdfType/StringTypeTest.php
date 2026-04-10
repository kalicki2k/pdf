<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\PdfType\StringType;
use Kalle\Pdf\Security\EncryptionAlgorithm;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class StringTypeTest extends TestCase
{
    #[Test]
    public function it_wraps_the_escaped_string_in_parentheses(): void
    {
        $value = new StringType("\\(Line 1)\n\t" . chr(8) . "\f");

        self::assertSame('(\\\\\\(Line 1\\)\\n\\t\\b\\f)', writePdfTypeToString($value));
    }

    #[Test]
    public function it_renders_non_windows_1252_strings_as_utf16_hex_strings(): void
    {
        $value = new StringType('漢');

        self::assertSame('<FEFF6F22>', writePdfTypeToString($value));
    }

    #[Test]
    public function it_renders_windows_1252_strings_as_encrypted_hex_when_an_object_encryptor_is_active(): void
    {
        $rendered = writePdfTypeToString(new StringType(
            'Hello',
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        ));

        self::assertMatchesRegularExpression('/^<[0-9A-F]+>$/', $rendered);
        self::assertNotSame('<48656C6C6F>', $rendered);
    }

    #[Test]
    public function it_renders_utf16_strings_as_encrypted_hex_when_an_object_encryptor_is_active(): void
    {
        $rendered = writePdfTypeToString(new StringType(
            '漢',
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        ));

        self::assertMatchesRegularExpression('/^<[0-9A-F]+>$/', $rendered);
        self::assertNotSame('<FEFF6F22>', $rendered);
    }

    #[Test]
    public function it_can_render_encrypted_strings_with_an_explicit_object_string_encryptor(): void
    {
        $rendered = writePdfTypeToString(new StringType(
            'Hello',
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        ));

        self::assertMatchesRegularExpression('/^<[0-9A-F]+>$/', $rendered);
        self::assertNotSame('<48656C6C6F>', $rendered);
    }

    #[Test]
    public function it_renders_plain_strings_without_an_explicit_encryptor(): void
    {
        self::assertSame('(Hello)', writePdfTypeToString(new StringType('Hello')));
    }

    #[Test]
    public function it_writes_the_same_bytes_as_the_render_helper(): void
    {
        $value = new StringType('Hello');

        self::assertSame('(Hello)', writePdfTypeToString($value));
    }
}
