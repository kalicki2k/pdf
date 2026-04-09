<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Render\RenderContext;
use Kalle\Pdf\Types\StringType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StringTypeTest extends TestCase
{
    #[Test]
    public function it_wraps_the_escaped_string_in_parentheses(): void
    {
        $value = new StringType("\\(Line 1)\n\t" . chr(8) . "\f");

        self::assertSame('(\\\\\\(Line 1\\)\\n\\t\\b\\f)', $value->render());
    }

    #[Test]
    public function it_renders_non_windows_1252_strings_as_utf16_hex_strings(): void
    {
        $value = new StringType('漢');

        self::assertSame('<FEFF6F22>', $value->render());
    }

    #[Test]
    public function it_renders_windows_1252_strings_as_encrypted_hex_when_an_object_encryptor_is_active(): void
    {
        $rendered = RenderContext::runWith(
            new StandardObjectEncryptor(
                new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ),
            static fn (): string => RenderContext::runInObject(7, static fn (): string => (new StringType('Hello'))->render()),
        );

        self::assertMatchesRegularExpression('/^<[0-9A-F]+>$/', $rendered);
        self::assertNotSame('<48656C6C6F>', $rendered);
    }

    #[Test]
    public function it_renders_utf16_strings_as_encrypted_hex_when_an_object_encryptor_is_active(): void
    {
        $rendered = RenderContext::runWith(
            new StandardObjectEncryptor(
                new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ),
            static fn (): string => RenderContext::runInObject(7, static fn (): string => (new StringType('漢'))->render()),
        );

        self::assertMatchesRegularExpression('/^<[0-9A-F]+>$/', $rendered);
        self::assertNotSame('<FEFF6F22>', $rendered);
    }

    #[Test]
    public function it_can_render_encrypted_strings_with_an_explicit_object_string_encryptor(): void
    {
        $rendered = (new StringType(
            'Hello',
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        ))->render();

        self::assertMatchesRegularExpression('/^<[0-9A-F]+>$/', $rendered);
        self::assertNotSame('<48656C6C6F>', $rendered);
    }
}
