<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionPermissions;
use Kalle\Pdf\Encryption\EncryptionVersionResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncryptDictionaryTest extends TestCase
{
    #[Test]
    public function it_renders_an_encrypt_dictionary_for_rc4_128(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.6));
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.6));
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
    public function it_renders_aes_256_entries_for_pdf_1_7(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.7));
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
    public function it_assigns_a_stable_document_id_pair(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        [$first, $second] = $document->getDocumentId();

        self::assertSame(32, strlen($first));
        self::assertSame($first, $second);
    }

    #[Test]
    public function it_rejects_rendering_without_initialized_security_handler_data(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $profile = (new EncryptionVersionResolver())->resolve(1.4, EncryptionAlgorithm::RC4_128);
        $encryptDictionary = new \Kalle\Pdf\Document\EncryptDictionary(7, $document, $profile);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encryption dictionary requires initialized security handler data.');

        $encryptDictionary->render();
    }
}
