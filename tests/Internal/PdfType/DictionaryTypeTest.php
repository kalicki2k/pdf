<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\PdfType\BooleanType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\StringType;
use Kalle\Pdf\Security\EncryptionAlgorithm;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class DictionaryTypeTest extends TestCase
{
    #[Test]
    public function it_renders_entries_in_insertion_order(): void
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Catalog'),
            'Count' => 2,
            'Open' => new BooleanType(true),
            'Version' => '1.4',
        ]);

        self::assertSame('<< /Type /Catalog /Count 2 /Open true /Version 1.4 >>', writePdfTypeToString($dictionary));
    }

    #[Test]
    public function it_can_add_entries_after_construction(): void
    {
        $dictionary = new DictionaryType(['Type' => new NameType('Pages')])
            ->add('Count', 3);

        self::assertSame('<< /Type /Pages /Count 3 >>', writePdfTypeToString($dictionary));
    }

    #[Test]
    public function it_writes_the_same_bytes_as_the_render_helper(): void
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Catalog'),
            'Count' => 2,
            'Open' => new BooleanType(true),
        ]);

        self::assertSame('<< /Type /Catalog /Count 2 /Open true >>', writePdfTypeToString($dictionary));
    }

    #[Test]
    public function it_can_render_nested_strings_with_an_explicit_object_string_encryptor(): void
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Info'),
            'Value' => new StringType('Hello'),
        ]);

        $rendered = writePdfTypeToString(
            $dictionary,
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith('<< /Type /Info /Value <', $rendered);
        self::assertStringNotContainsString('(Hello)', $rendered);
    }
}
