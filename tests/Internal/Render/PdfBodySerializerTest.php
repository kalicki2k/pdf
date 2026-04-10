<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Render;

use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfBodySerializer;
use Kalle\Pdf\Render\PdfEncryption;
use Kalle\Pdf\Render\PdfFileStructure;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\PdfSerializationPlan;
use Kalle\Pdf\Render\PdfTrailer;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfBodySerializerTest extends TestCase
{
    #[Test]
    public function it_writes_document_objects_and_returns_their_offsets(): void
    {
        $serializer = new PdfBodySerializer();
        $output = new StringPdfOutput();

        $offsets = $serializer->write(
            new PdfSerializationPlan(
                objects: [
                    $this->indirectObject(1, '<< /Type /Catalog >>'),
                    $this->indirectObject(3, '<< /Type /Pages /Count 0 >>'),
                ],
                fileStructure: new PdfFileStructure(1.4, new PdfTrailer(1, 3, null, ['id-a', 'id-b'])),
            ),
            $output,
        );

        self::assertSame(0, $offsets->entries()[1]);
        self::assertSame(strpos($output->contents(), "3 0 obj\n"), $offsets->entries()[3]);
        self::assertSame("1 0 obj\n<< /Type /Catalog >>\nendobj\n3 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\n", $output->contents());
    }

    #[Test]
    public function it_uses_the_plan_encryption_when_writing_encryptable_objects(): void
    {
        $serializer = new PdfBodySerializer();
        $output = new StringPdfOutput();

        $serializer->write(
            new PdfSerializationPlan(
                objects: [
                    new class (7) extends IndirectObject implements EncryptableIndirectObject {
                        protected function writeObject(PdfOutput $output): void
                        {
                            $output->write("7 0 obj\n<< /Length 20 >>\nstream\nplain-stream-payload\nendstream\nendobj\n");
                        }

                        public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
                        {
                            $encrypted = $objectEncryptor->encryptString($this->id, 'plain-stream-payload');

                            $output->write(
                                "7 0 obj\n<< /Length " . strlen($encrypted) . " >>\nstream\n" . $encrypted . "\nendstream\nendobj\n",
                            );
                        }
                    },
                ],
                fileStructure: new PdfFileStructure(1.4, new PdfTrailer(1, 3, 7, ['id-a', 'id-b'])),
                encryption: new PdfEncryption(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
            ),
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

            protected function writeObject(PdfOutput $output): void
            {
                $output->write($this->id . " 0 obj\n" . $this->body . "\nendobj\n");
            }
        };
    }
}
