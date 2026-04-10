<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Model\Document\EncryptDictionary;
use Kalle\Pdf\Model\Document\Info;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Render\PdfObjectOffsets;
use Kalle\Pdf\Render\PdfObjectSerializer;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Security\EncryptionOptions;
use Kalle\Pdf\Types\StringType;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfObjectSerializerTest extends TestCase
{
    #[Test]
    public function it_writes_objects_and_returns_their_offsets(): void
    {
        $serializer = new PdfObjectSerializer();
        $output = new StringPdfOutput();
        $firstObject = new class (1) extends IndirectObject {
            protected function writeObject(PdfOutput $output): void
            {
                $output->write("1 0 obj\nalpha\nendobj\n");
            }
        };
        $secondObject = new class (3) extends IndirectObject {
            protected function writeObject(PdfOutput $output): void
            {
                $output->write("3 0 obj\nbeta\nendobj\n");
            }
        };

        $offsets = $serializer->writeObjects([$firstObject, $secondObject], $output);
        $contents = $output->contents();

        self::assertInstanceOf(PdfObjectOffsets::class, $offsets);
        self::assertSame(0, $offsets->entries()[1]);
        self::assertSame(strpos($contents, "3 0 obj\n"), $offsets->entries()[3]);
        self::assertStringContainsString("1 0 obj\nalpha\nendobj\n3 0 obj\nbeta\nendobj\n", $contents);
    }

    #[Test]
    public function it_writes_objects_directly_to_the_provided_output(): void
    {
        $serializer = new PdfObjectSerializer();
        $output = new class () implements PdfOutput {
            public string $contents = '';

            public function write(string $bytes): void
            {
                $this->contents .= $bytes;
            }

            public function offset(): int
            {
                return strlen($this->contents);
            }
        };

        $serializer->writeObjects([new class (11, $output) extends IndirectObject {
            public function __construct(int $id, private readonly PdfOutput $expectedOutput)
            {
                parent::__construct($id);
            }

            protected function writeObject(PdfOutput $output): void
            {
                if ($output !== $this->expectedOutput) {
                    throw new LogicException('Expected direct write to the provided output');
                }

                $output->write("11 0 obj\ndirect\nendobj\n");
            }
        }], $output);

        self::assertSame("11 0 obj\ndirect\nendobj\n", $output->contents);
    }

    #[Test]
    public function it_uses_the_encrypted_object_write_path_when_available(): void
    {
        $objectEncryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $serializer = new PdfObjectSerializer($objectEncryptor);
        $output = new class () implements PdfOutput {
            public string $contents = '';

            public function write(string $bytes): void
            {
                $this->contents .= $bytes;
            }

            public function offset(): int
            {
                return strlen($this->contents);
            }
        };

        $serializer->writeObjects([new class (17, $output) extends IndirectObject implements EncryptableIndirectObject {
            public function __construct(int $id, private readonly PdfOutput $expectedOutput)
            {
                parent::__construct($id);
            }

            protected function writeObject(PdfOutput $output): void
            {
                throw new LogicException('write() should not be called');
            }

            public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
            {
                if ($output !== $this->expectedOutput) {
                    throw new LogicException('Expected encrypted write to use the provided output directly');
                }

                $encrypted = $objectEncryptor->encryptString($this->id, 'abc');
                $output->write("17 0 obj\n<< /Length " . strlen($encrypted) . " >>\nstream\n" . $encrypted . "\nendstream\nendobj\n");
            }
        }], $output);

        self::assertStringStartsWith("17 0 obj\n<< /Length ", $output->contents);
        self::assertStringContainsString("\nendstream\nendobj\n", $output->contents);
        self::assertStringNotContainsString('abc', $output->contents);
    }

    #[Test]
    public function it_can_write_non_stream_objects_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            title: 'Spec',
            author: 'Kalle',
        );
        $serializer = new PdfObjectSerializer(
            new StandardObjectEncryptor(
                new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ),
        );
        $output = new StringPdfOutput();

        $serializer->writeObjects([new Info(7, $document)], $output);

        self::assertStringStartsWith("7 0 obj\n<< /Title ", $output->contents());
        self::assertStringNotContainsString('(Spec)', $output->contents());
        self::assertStringNotContainsString('(Kalle)', $output->contents());
    }

    #[Test]
    public function it_encrypts_strings_in_non_stream_objects_without_buffering_them_as_streams(): void
    {
        $serializer = new PdfObjectSerializer(
            new StandardObjectEncryptor(
                new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ),
        );
        $output = new StringPdfOutput();

        $serializer->writeObjects([new class (7) extends IndirectObject {
            protected function writeObject(PdfOutput $output): void
            {
                $output->write(
                    $this->id . " 0 obj\n<< /Value " . (new StringType('plain-text'))->render() . " >>\nendobj\n",
                );
            }

            protected function writeObjectWithStringEncryptor(PdfOutput $output, ObjectStringEncryptor $encryptor): void
            {
                $output->write(
                    $this->id . " 0 obj\n<< /Value "
                    . (new StringType('plain-text'))->render($encryptor)
                    . " >>\nendobj\n",
                );
            }
        }], $output);

        self::assertStringStartsWith("7 0 obj\n<< /Value ", $output->contents());
        self::assertStringNotContainsString('plain-text', $output->contents());
    }

    #[Test]
    public function it_writes_encrypt_dictionaries_without_modifying_them_when_encryption_is_active(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->encrypt(new EncryptionOptions('user', 'owner'));
        $profile = new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3);
        $encryptDictionary = new EncryptDictionary(15, $document, $profile);
        $serializer = new PdfObjectSerializer(
            new StandardObjectEncryptor(
                $profile,
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ),
        );
        $output = new StringPdfOutput();

        $serializer->writeObjects([$encryptDictionary], $output);

        self::assertSame($encryptDictionary->render(), $output->contents());
    }
}
