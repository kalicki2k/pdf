<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Encryption\Standard;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Profile\EncryptionVersionResolver;
use Kalle\Pdf\Encryption\Standard\EncryptDictionary;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Security\EncryptionOptions;
use Kalle\Pdf\Security\EncryptionPermissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncryptDictionaryTest extends TestCase
{
    #[Test]
    public function it_renders_an_encrypt_dictionary_for_rc4_40(): void
    {
        $document = new Document(profile: Profile::pdf13());
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            permissions: EncryptionPermissions::readOnly(),
            algorithm: EncryptionAlgorithm::RC4_40,
        ));

        $encryptDictionary = $document->encryptDictionary;

        self::assertNotNull($encryptDictionary);
        self::assertStringContainsString('/Filter /Standard', $encryptDictionary->render());
        self::assertStringContainsString('/V 1', $encryptDictionary->render());
        self::assertStringContainsString('/R 2', $encryptDictionary->render());
        self::assertStringContainsString('/Length 40', $encryptDictionary->render());
        self::assertStringContainsString('/P -4', $encryptDictionary->render());
    }

    #[Test]
    public function it_renders_an_encrypt_dictionary_for_rc4_128(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            permissions: EncryptionPermissions::readOnly(),
            algorithm: EncryptionAlgorithm::RC4_128,
        ));

        $encryptDictionary = $document->encryptDictionary;

        self::assertNotNull($encryptDictionary);
        self::assertStringContainsString('/Filter /Standard', $encryptDictionary->render());
        self::assertStringContainsString('/V 2', $encryptDictionary->render());
        self::assertStringContainsString('/R 3', $encryptDictionary->render());
        self::assertStringContainsString('/Length 128', $encryptDictionary->render());
        self::assertStringContainsString('/P -4', $encryptDictionary->render());
    }

    #[Test]
    public function it_writes_the_encrypt_reference_into_the_trailer(): void
    {
        $document = new Document(profile: Profile::standard(1.6));
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
        ));

        $pdf = $document->render();

        self::assertStringContainsString('/Encrypt ', $pdf);
        self::assertStringContainsString('/Filter /Standard', $pdf);
        self::assertStringContainsString('/ID [<', $pdf);
    }

    #[Test]
    public function it_renders_aes_128_crypt_filter_entries_for_pdf_1_6(): void
    {
        $document = new Document(profile: Profile::standard(1.6));
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            algorithm: EncryptionAlgorithm::AES_128,
        ));

        $encryptDictionary = $document->encryptDictionary;

        self::assertNotNull($encryptDictionary);
        self::assertStringContainsString('/V 4', $encryptDictionary->render());
        self::assertStringContainsString('/R 4', $encryptDictionary->render());
        self::assertStringContainsString('/CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>', $encryptDictionary->render());
        self::assertStringContainsString('/StmF /StdCF', $encryptDictionary->render());
        self::assertStringContainsString('/StrF /StdCF', $encryptDictionary->render());
    }

    #[Test]
    public function it_rejects_rc4_40_encrypt_dictionaries_for_pdf_1_2(): void
    {
        $document = new Document(profile: Profile::pdf12());
        $encryptDictionary = new EncryptDictionary(
            7,
            $document,
            new EncryptionProfile(EncryptionAlgorithm::RC4_40, 40, 1, 2),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.2 does not allow RC4 40-bit encryption. PDF 1.3 or higher is required.');

        $encryptDictionary->render();
    }

    #[Test]
    public function it_rejects_aes_128_encrypt_dictionaries_for_pdf_1_5(): void
    {
        $document = new Document(profile: Profile::pdf15());
        $encryptDictionary = new EncryptDictionary(
            7,
            $document,
            new EncryptionProfile(EncryptionAlgorithm::AES_128, 128, 4, 4),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.5 does not allow AES-128 encryption. PDF 1.6 or higher is required.');

        $encryptDictionary->render();
    }

    #[Test]
    public function it_renders_aes_256_entries_for_pdf_1_7(): void
    {
        $document = new Document(profile: Profile::standard(1.7));
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            algorithm: EncryptionAlgorithm::AES_256,
        ));

        $encryptDictionary = $document->encryptDictionary;

        self::assertNotNull($encryptDictionary);
        self::assertStringContainsString('/V 5', $encryptDictionary->render());
        self::assertStringContainsString('/R 5', $encryptDictionary->render());
        self::assertStringContainsString('/OE <', $encryptDictionary->render());
        self::assertStringContainsString('/UE <', $encryptDictionary->render());
        self::assertStringContainsString('/Perms <', $encryptDictionary->render());
        self::assertStringContainsString('/CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>', $encryptDictionary->render());
    }

    #[Test]
    public function it_rejects_aes_256_encrypt_dictionaries_for_pdf_1_6(): void
    {
        $document = new Document(profile: Profile::pdf16());
        $encryptDictionary = new EncryptDictionary(
            7,
            $document,
            new EncryptionProfile(EncryptionAlgorithm::AES_256, 256, 5, 5),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.6 does not allow AES-256 encryption. PDF 1.7 or higher is required.');

        $encryptDictionary->render();
    }

    #[Test]
    public function it_assigns_a_stable_document_id_pair(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        [$first, $second] = $document->getDocumentId();

        self::assertSame(32, strlen($first));
        self::assertSame($first, $second);
    }

    #[Test]
    public function it_rejects_rendering_without_initialized_security_handler_data(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $profile = (new EncryptionVersionResolver())->resolve(1.4, EncryptionAlgorithm::RC4_128);
        $encryptDictionary = new EncryptDictionary(7, $document, $profile);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption dictionary requires initialized security handler data.');

        $encryptDictionary->render();
    }
}
