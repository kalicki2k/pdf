<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Render\PdfEncryption;
use Kalle\Pdf\Render\PdfFileStructure;
use Kalle\Pdf\Render\PdfObjectEncryptorFactory;
use Kalle\Pdf\Render\PdfSerializationPlan;
use Kalle\Pdf\Render\PdfTrailer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfObjectEncryptorFactoryTest extends TestCase
{
    #[Test]
    public function it_returns_null_without_encryption_data(): void
    {
        $encryptor = (new PdfObjectEncryptorFactory())->create(
            new PdfSerializationPlan(
                objects: [],
                fileStructure: new PdfFileStructure(1.4, new PdfTrailer(1, 3, null, ['id-a', 'id-b'])),
            ),
        );

        self::assertNull($encryptor);
    }

    #[Test]
    public function it_returns_null_for_profiles_without_object_encryption_support(): void
    {
        $encryptor = (new PdfObjectEncryptorFactory())->create(
            new PdfSerializationPlan(
                objects: [],
                fileStructure: new PdfFileStructure(1.4, new PdfTrailer(1, 3, 4, ['id-a', 'id-b'])),
                encryption: new PdfEncryption(
                    new EncryptionProfile(EncryptionAlgorithm::AUTO, 0, 0, 0),
                    new StandardSecurityHandlerData('', '', 'secret', -4),
                ),
            ),
        );

        self::assertNull($encryptor);
    }

    #[Test]
    public function it_creates_an_encryptor_for_supported_profiles(): void
    {
        $encryptor = (new PdfObjectEncryptorFactory())->create(
            new PdfSerializationPlan(
                objects: [],
                fileStructure: new PdfFileStructure(1.4, new PdfTrailer(1, 3, 4, ['id-a', 'id-b'])),
                encryption: new PdfEncryption(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
            ),
        );

        self::assertNotNull($encryptor);
        self::assertTrue($encryptor->supportsObjectEncryption());
    }
}
