<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfIndirectObjectWriter;
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
