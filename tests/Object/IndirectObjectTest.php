<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Object;

use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\StringType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndirectObjectTest extends TestCase
{
    #[Test]
    public function it_exposes_the_assigned_object_id(): void
    {
        $object = new class (42) extends IndirectObject {
            protected function writeObject(PdfOutput $output): void
            {
                $output->write('dummy');
            }
        };

        self::assertSame(42, $object->id);
    }

    #[Test]
    public function it_writes_its_rendered_bytes_to_a_pdf_output_by_default(): void
    {
        $object = new class (42) extends IndirectObject {
            protected function writeObject(PdfOutput $output): void
            {
                $output->write('dummy');
            }
        };
        $output = new StringPdfOutput();

        $object->write($output);

        self::assertSame('dummy', $output->contents());
    }

    #[Test]
    public function it_can_write_bytes_with_an_explicit_object_string_encryptor(): void
    {
        $object = new class (42) extends IndirectObject {
            protected function writeObject(PdfOutput $output): void
            {
                $output->write('dummy');
            }

            protected function writeObjectWithStringEncryptor(PdfOutput $output, ObjectStringEncryptor $encryptor): void
            {
                $output->write($encryptor->encrypt('dummy'));
            }
        };
        $output = new StringPdfOutput();

        $object->writeWithStringEncryptor(
            $output,
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                42,
            ),
        );

        self::assertNotSame('dummy', $output->contents());
    }

    #[Test]
    public function it_can_render_a_dictionary_object_with_an_explicit_string_encryptor(): void
    {
        $object = new class (42) extends IndirectObject {
            protected function writeObject(PdfOutput $output): void
            {
                $this->writeDictionaryObject(
                    $output,
                    new DictionaryType([
                        'Value' => new StringType('plain-text'),
                    ]),
                );
            }

            protected function writeObjectWithStringEncryptor(PdfOutput $output, ObjectStringEncryptor $encryptor): void
            {
                $this->writeDictionaryObject(
                    $output,
                    new DictionaryType([
                        'Value' => new StringType('plain-text'),
                    ]),
                    $encryptor,
                );
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
