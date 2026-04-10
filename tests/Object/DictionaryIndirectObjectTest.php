<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Object;

use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\StringType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DictionaryIndirectObjectTest extends TestCase
{
    #[Test]
    public function it_renders_a_dictionary_object_by_default(): void
    {
        $object = new class (42) extends DictionaryIndirectObject {
            protected function dictionary(): DictionaryType
            {
                return new DictionaryType([
                    'Value' => new StringType('plain-text'),
                ]);
            }
        };

        self::assertSame("42 0 obj\n<< /Value (plain-text) >>\nendobj\n", $object->render());
    }

    #[Test]
    public function it_renders_a_dictionary_object_with_an_explicit_string_encryptor(): void
    {
        $object = new class (42) extends DictionaryIndirectObject {
            protected function dictionary(): DictionaryType
            {
                return new DictionaryType([
                    'Value' => new StringType('plain-text'),
                ]);
            }
        };

        $rendered = $object->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                42,
            ),
        );

        self::assertStringContainsString('42 0 obj', $rendered);
        self::assertStringContainsString('endobj', $rendered);
        self::assertStringNotContainsString('(plain-text)', $rendered);
    }
}
