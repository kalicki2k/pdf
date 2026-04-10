<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\BooleanType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayTypeTest extends TestCase
{
    #[Test]
    public function it_renders_scalar_and_value_entries_in_order(): void
    {
        $value = new ArrayType([
            new NameType('Type'),
            12,
            3.5,
            new BooleanType(true),
        ]);

        self::assertSame('[/Type 12 3.5 true]', $value->render());
    }

    #[Test]
    public function it_can_render_nested_strings_with_an_explicit_object_string_encryptor(): void
    {
        $value = new ArrayType([
            new NameType('Value'),
            new StringType('Hello'),
        ]);

        $rendered = $value->render(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith('[/Value <', $rendered);
        self::assertStringNotContainsString('(Hello)', $rendered);
    }
}
