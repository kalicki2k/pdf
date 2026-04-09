<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Object;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndirectObjectTest extends TestCase
{
    #[Test]
    public function it_exposes_the_assigned_object_id(): void
    {
        $object = new class (42) extends IndirectObject {
            public function render(): string
            {
                return 'dummy';
            }
        };

        self::assertSame(42, $object->id);
    }

    #[Test]
    public function it_writes_its_rendered_bytes_to_a_pdf_output_by_default(): void
    {
        $object = new class (42) extends IndirectObject {
            public function render(): string
            {
                return 'dummy';
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
            public function render(): string
            {
                return 'dummy';
            }

            public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
            {
                return $encryptor?->encrypt('dummy') ?? 'dummy';
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
}
