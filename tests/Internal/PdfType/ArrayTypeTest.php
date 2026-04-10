<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\BooleanType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\StringType;
use Kalle\Pdf\Security\EncryptionAlgorithm;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

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
    public function it_writes_the_same_bytes_as_the_render_helper(): void
    {
        $value = new ArrayType([
            new NameType('Type'),
            12,
            3.5,
            new BooleanType(true),
        ]);

        self::assertSame($value->render(), writePdfTypeToString($value));
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
