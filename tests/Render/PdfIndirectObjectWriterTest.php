<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\EncryptDictionary;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Render\PdfIndirectObjectWriter;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfIndirectObjectWriterTest extends TestCase
{
    #[Test]
    public function it_writes_a_single_indirect_object_to_the_output(): void
    {
        $writer = new PdfIndirectObjectWriter();
        $output = new StringPdfOutput();

        $writer->write($this->indirectObject(5, '<< /Type /Example >>'), $output);

        self::assertSame("5 0 obj\n<< /Type /Example >>\nendobj\n", $output->contents());
    }

    #[Test]
    public function it_encrypts_stream_objects_when_an_encryptor_is_available(): void
    {
        $writer = new PdfIndirectObjectWriter(
            new StandardObjectEncryptor(
                new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ),
        );
        $output = new StringPdfOutput();

        $writer->write(
            $this->indirectObject(7, "<< /Length 20 >>\nstream\nplain-stream-payload\nendstream"),
            $output,
        );

        self::assertStringContainsString("7 0 obj\n<< /Length 20 >>\nstream\n", $output->contents());
        self::assertStringContainsString("\nendstream\nendobj\n", $output->contents());
        self::assertStringNotContainsString('plain-stream-payload', $output->contents());
    }

    #[Test]
    public function it_uses_the_object_write_path_when_available(): void
    {
        $writer = new PdfIndirectObjectWriter();
        $output = new StringPdfOutput();

        $writer->write(new class (9) extends IndirectObject {
            public function write(PdfOutput $output): void
            {
                $output->write("9 0 obj\nwritten\nendobj\n");
            }

            public function render(): string
            {
                throw new \LogicException('render() should not be called');
            }
        }, $output);

        self::assertSame("9 0 obj\nwritten\nendobj\n", $output->contents());
    }

    #[Test]
    public function it_writes_unencrypted_objects_directly_to_the_provided_output(): void
    {
        $writer = new PdfIndirectObjectWriter();
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

        $writer->write(new class (11, $output) extends IndirectObject {
            public function __construct(int $id, private readonly PdfOutput $expectedOutput)
            {
                parent::__construct($id);
            }

            public function write(PdfOutput $output): void
            {
                if ($output !== $this->expectedOutput) {
                    throw new \LogicException('Expected direct write to the provided output');
                }

                $output->write("11 0 obj\ndirect\nendobj\n");
            }

            public function render(): string
            {
                throw new \LogicException('render() should not be called');
            }
        }, $output);

        self::assertSame("11 0 obj\ndirect\nendobj\n", $output->contents);
    }

    #[Test]
    public function it_writes_encrypt_dictionaries_directly_even_when_encryption_is_active(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->encrypt(new \Kalle\Pdf\Encryption\EncryptionOptions('user', 'owner'));
        $profile = new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3);
        $encryptDictionary = new EncryptDictionary(15, $document, $profile);
        $writer = new PdfIndirectObjectWriter(
            new StandardObjectEncryptor(
                $profile,
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ),
        );
        $output = new StringPdfOutput();

        $writer->write($encryptDictionary, $output);

        self::assertSame($encryptDictionary->render(), $output->contents());
    }

    #[Test]
    public function it_uses_the_encrypted_object_write_path_when_available(): void
    {
        $objectEncryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $writer = new PdfIndirectObjectWriter($objectEncryptor);
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

        $writer->write(new class (17, $output) extends IndirectObject implements EncryptableIndirectObject {
            public function __construct(int $id, private readonly PdfOutput $expectedOutput)
            {
                parent::__construct($id);
            }

            public function write(PdfOutput $output): void
            {
                throw new \LogicException('write() should not be called');
            }

            public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
            {
                if ($output !== $this->expectedOutput) {
                    throw new \LogicException('Expected encrypted write to use the provided output directly');
                }

                $encrypted = $objectEncryptor->encryptString($this->id, 'abc');
                $output->write("17 0 obj\n<< /Length " . strlen($encrypted) . " >>\nstream\n" . $encrypted . "\nendstream\nendobj\n");
            }

            public function render(): string
            {
                throw new \LogicException('render() should not be called');
            }
        }, $output);

        self::assertStringStartsWith("17 0 obj\n<< /Length ", $output->contents);
        self::assertStringContainsString("\nendstream\nendobj\n", $output->contents);
        self::assertStringNotContainsString('abc', $output->contents);
    }

    private function indirectObject(int $id, string $body): IndirectObject
    {
        return new class ($id, $body) extends IndirectObject {
            public function __construct(int $id, private readonly string $body)
            {
                parent::__construct($id);
            }

            public function render(): string
            {
                return $this->id . " 0 obj\n" . $this->body . "\nendobj\n";
            }
        };
    }
}
