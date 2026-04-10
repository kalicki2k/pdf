<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Object;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Object\DeferredLengthStreamIndirectObject;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeferredLengthStreamIndirectObjectTest extends TestCase
{
    #[Test]
    public function it_keeps_the_direct_length_path_without_a_deferred_length_object(): void
    {
        $object = $this->createStreamObject('BT');

        self::assertSame(
            "7 0 obj\n<< /Length 2 >>\nstream\nBT\nendstream\nendobj\n",
            $object->render(),
        );
    }

    #[Test]
    public function it_writes_a_deferred_stream_length_object_after_stream_serialization(): void
    {
        $object = $this->createStreamObject('BT');
        $object->prepareLengthObject(9);
        $output = new StringPdfOutput();

        $object->write($output);

        self::assertSame(
            "7 0 obj\n<< /Length 9 0 R >>\nstream\nBT\nendstream\nendobj\n",
            $output->contents(),
        );
        self::assertNotNull($object->getLengthObject());
        self::assertSame("9 0 obj\n2\nendobj\n", $object->getLengthObject()->render());
    }

    #[Test]
    public function it_tracks_the_encrypted_length_without_a_counting_prepass_when_a_deferred_length_object_exists(): void
    {
        $object = $this->createStreamObject('BT');
        $object->prepareLengthObject(9);
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::AES_128, 128, 4, 4),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $object->writeEncrypted($output, $encryptor);

        self::assertStringStartsWith(
            "7 0 obj\n<< /Length 9 0 R >>\nstream\n",
            $output->contents(),
        );
        self::assertStringEndsWith("\nendstream\nendobj\n", $output->contents());
        self::assertNotNull($object->getLengthObject());
        self::assertSame(
            $encryptor->encryptedByteLength(2),
            $object->getLengthObject()->getLength(),
        );
    }

    private function createStreamObject(string $payload): DeferredLengthStreamIndirectObject
    {
        return new class (7, $payload) extends DeferredLengthStreamIndirectObject {
            public function __construct(int $id, private readonly string $payload)
            {
                parent::__construct($id);
            }

            protected function streamDictionary(int | ReferenceType $length): DictionaryType
            {
                return new DictionaryType([
                    'Length' => $length,
                ]);
            }

            protected function writeStreamContents(PdfOutput $output): void
            {
                $output->write($this->payload);
            }
        };
    }
}
