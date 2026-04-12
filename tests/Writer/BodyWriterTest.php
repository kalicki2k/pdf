<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Writer;

use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Encryption\Aes128Cipher;
use Kalle\Pdf\Encryption\Algorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\ObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Writer\BodyWriter;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileStructure;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\StringOutput;
use Kalle\Pdf\Writer\Trailer;
use PHPUnit\Framework\TestCase;

final class BodyWriterTest extends TestCase
{
    public function testItWritesIndirectObjectsAndReturnsTheirOffsets(): void
    {
        $writer = new BodyWriter();
        $output = new StringOutput();
        $plan = new DocumentSerializationPlan(
            objects: [
                new IndirectObject(1, '<< /Type /Catalog /Pages 2 0 R >>'),
                new IndirectObject(2, "<< /Type /Pages /Count 0 /Kids [] >>\n"),
            ],
            fileStructure: new FileStructure(
                version: Version::V1_4,
                trailer: new Trailer(size: 3, rootObjectId: 1),
            ),
        );

        $offsets = $writer->write($plan, $output);

        self::assertSame([1 => 0, 2 => 49], $offsets);
        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Count 0 /Kids [] >>\nendobj\n",
            $output->contents(),
        );
    }

    public function testItUpdatesStructuredStreamLengthsAfterAesEncryption(): void
    {
        $writer = new BodyWriter();
        $output = new StringOutput();
        $plan = new DocumentSerializationPlan(
            objects: [
                IndirectObject::stream(1, '<< /Length 4 >>', 'data'),
            ],
            fileStructure: new FileStructure(
                version: Version::V1_6,
                trailer: new Trailer(size: 2, rootObjectId: 1),
            ),
            objectEncryptor: new ObjectEncryptor(
                new EncryptionProfile(Algorithm::AES_128, 128, 4, 4),
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                aes128Cipher: new Aes128Cipher(static fn (): string => str_repeat("\x01", 16)),
            ),
        );

        $writer->write($plan, $output);

        self::assertStringContainsString("1 0 obj\n<< /Length 32 >>\nstream\n", $output->contents());
        self::assertStringContainsString("\nendstream\nendobj\n", $output->contents());
    }
}
