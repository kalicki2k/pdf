<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionVersionResolver;
use Kalle\Pdf\Internal\Encryption\Profile\PermissionBitsResolver;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Internal\Security\EncryptionOptions;
use Kalle\Pdf\Internal\Security\EncryptionPermissions;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncryptionVersionResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_auto_to_rc4_40_for_pdf_1_3(): void
    {
        $resolver = new EncryptionVersionResolver();
        $profile = $resolver->resolve(1.3, EncryptionAlgorithm::AUTO);

        self::assertSame(EncryptionAlgorithm::RC4_40, $profile->algorithm);
        self::assertSame(40, $profile->keyLengthInBits);
        self::assertSame(1, $profile->dictionaryVersion);
        self::assertSame(2, $profile->revision);
    }

    #[Test]
    public function it_resolves_auto_to_rc4_128_for_pdf_1_4(): void
    {
        $resolver = new EncryptionVersionResolver();
        $profile = $resolver->resolve(1.4, EncryptionAlgorithm::AUTO);

        self::assertSame(EncryptionAlgorithm::RC4_128, $profile->algorithm);
        self::assertSame(128, $profile->keyLengthInBits);
        self::assertSame(2, $profile->dictionaryVersion);
        self::assertSame(3, $profile->revision);
    }

    #[Test]
    public function it_resolves_auto_to_aes_128_for_pdf_1_6(): void
    {
        $resolver = new EncryptionVersionResolver();
        $profile = $resolver->resolve(1.6, EncryptionAlgorithm::AUTO);

        self::assertSame(EncryptionAlgorithm::AES_128, $profile->algorithm);
        self::assertSame(128, $profile->keyLengthInBits);
        self::assertSame(4, $profile->dictionaryVersion);
        self::assertSame(4, $profile->revision);
    }

    #[Test]
    public function it_rejects_aes_128_for_pdf_1_4(): void
    {
        $resolver = new EncryptionVersionResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AES 128-bit encryption requires PDF 1.6 or newer.');

        $resolver->resolve(1.4, EncryptionAlgorithm::AES_128);
    }

    #[Test]
    public function it_rejects_aes_128_via_document_encrypt_for_pdf_1_5(): void
    {
        $document = new Document(profile: Profile::pdf15());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.5 does not allow AES-128 encryption. PDF 1.6 or higher is required.');

        $document->encrypt(new EncryptionOptions(
            userPassword: 'user-secret',
            ownerPassword: 'owner-secret',
            algorithm: EncryptionAlgorithm::AES_128,
        ));
    }

    #[Test]
    public function it_rejects_aes_128_encryption_algorithms_for_pdf_1_5(): void
    {
        $document = new Document(profile: Profile::pdf15());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.5 does not allow AES-128 encryption. PDF 1.6 or higher is required.');

        $document->assertAllowsEncryptionAlgorithm(EncryptionAlgorithm::AES_128);
    }

    #[Test]
    public function it_resolves_rc4_128_for_pdf_1_4_when_requested_explicitly(): void
    {
        $resolver = new EncryptionVersionResolver();
        $profile = $resolver->resolve(1.4, EncryptionAlgorithm::RC4_128);

        self::assertSame(EncryptionAlgorithm::RC4_128, $profile->algorithm);
        self::assertSame(128, $profile->keyLengthInBits);
        self::assertSame(2, $profile->dictionaryVersion);
        self::assertSame(3, $profile->revision);
    }

    #[Test]
    public function it_rejects_rc4_128_for_pdf_1_3_when_requested_explicitly(): void
    {
        $resolver = new EncryptionVersionResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RC4 128-bit encryption requires PDF 1.4 or newer.');

        $resolver->resolve(1.3, EncryptionAlgorithm::RC4_128);
    }

    #[Test]
    public function it_rejects_rc4_40_for_pdf_1_0(): void
    {
        $resolver = new EncryptionVersionResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RC4 40-bit encryption requires PDF 1.3 or newer.');

        $resolver->resolve(1.0, EncryptionAlgorithm::RC4_40);
    }

    #[Test]
    public function it_resolves_and_stores_the_rc4_40_profile_via_encrypt_options_for_pdf_1_3(): void
    {
        $document = new Document(profile: Profile::pdf13());
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user-secret',
            ownerPassword: 'owner-secret',
            algorithm: EncryptionAlgorithm::RC4_40,
        ));

        self::assertNotNull($document->getEncryptionProfile());
        self::assertSame(EncryptionAlgorithm::RC4_40, $document->getEncryptionProfile()?->algorithm);
    }

    #[Test]
    public function it_rejects_rc4_40_via_document_encrypt_for_pdf_1_2(): void
    {
        $document = new Document(profile: Profile::pdf12());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.2 does not allow RC4 40-bit encryption. PDF 1.3 or higher is required.');

        $document->encrypt(new EncryptionOptions(
            userPassword: 'user-secret',
            ownerPassword: 'owner-secret',
            algorithm: EncryptionAlgorithm::RC4_40,
        ));
    }

    #[Test]
    public function it_rejects_rc4_40_encryption_algorithms_for_pdf_1_2(): void
    {
        $document = new Document(profile: Profile::pdf12());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.2 does not allow RC4 40-bit encryption. PDF 1.3 or higher is required.');

        $document->assertAllowsEncryptionAlgorithm(EncryptionAlgorithm::RC4_40);
    }

    #[Test]
    public function it_resolves_aes_256_for_pdf_1_7(): void
    {
        $resolver = new EncryptionVersionResolver();
        $profile = $resolver->resolve(1.7, EncryptionAlgorithm::AES_256);

        self::assertSame(EncryptionAlgorithm::AES_256, $profile->algorithm);
        self::assertSame(256, $profile->keyLengthInBits);
        self::assertSame(5, $profile->dictionaryVersion);
        self::assertSame(5, $profile->revision);
    }

    #[Test]
    public function it_rejects_aes_256_for_pdf_1_6(): void
    {
        $resolver = new EncryptionVersionResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AES 256-bit encryption requires PDF 1.7 or newer.');

        $resolver->resolve(1.6, EncryptionAlgorithm::AES_256);
    }

    #[Test]
    public function it_rejects_aes_256_via_document_encrypt_for_pdf_1_6(): void
    {
        $document = new Document(profile: Profile::pdf16());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.6 does not allow AES-256 encryption. PDF 1.7 or higher is required.');

        $document->encrypt(new EncryptionOptions(
            userPassword: 'user-secret',
            ownerPassword: 'owner-secret',
            algorithm: EncryptionAlgorithm::AES_256,
        ));
    }

    #[Test]
    public function it_rejects_aes_256_encryption_algorithms_for_pdf_1_6(): void
    {
        $document = new Document(profile: Profile::pdf16());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF version 1.6 does not allow AES-256 encryption. PDF 1.7 or higher is required.');

        $document->assertAllowsEncryptionAlgorithm(EncryptionAlgorithm::AES_256);
    }

    #[Test]
    public function it_resolves_and_stores_the_encryption_profile_via_encrypt_options(): void
    {
        $document = new Document(profile: Profile::standard(1.6));
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user-secret',
            ownerPassword: 'owner-secret',
        ));

        self::assertNotNull($document->getEncryptionProfile());
        self::assertSame(EncryptionAlgorithm::AES_128, $document->getEncryptionProfile()?->algorithm);
    }

    #[Test]
    public function it_allows_documents_to_store_encryption_options_and_resolve_the_profile(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user-secret',
            ownerPassword: 'owner-secret',
            permissions: EncryptionPermissions::readOnly(),
        ));

        self::assertNotNull($document->getEncryptionOptions());
        self::assertSame('user-secret', $document->getEncryptionOptions()?->userPassword);
        self::assertFalse($document->getEncryptionOptions()?->permissions->print ?? true);
        self::assertSame(EncryptionAlgorithm::RC4_128, $document->getEncryptionProfile()?->algorithm);
    }

    #[Test]
    public function it_rejects_empty_encryption_passwords(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either a user password or an owner password must be provided.');

        new EncryptionOptions(
            userPassword: '',
            ownerPassword: '',
        );
    }

    #[Test]
    public function it_resolves_permission_bits_for_read_only_permissions(): void
    {
        $resolver = new EncryptionVersionResolver();
        $permissionBitsResolver = new PermissionBitsResolver();
        $profile = $resolver->resolve(1.4, EncryptionAlgorithm::RC4_128);

        self::assertSame(-4, $permissionBitsResolver->resolve(EncryptionPermissions::readOnly(), $profile));
    }

    #[Test]
    public function it_resolves_permission_bits_for_full_permissions(): void
    {
        $resolver = new EncryptionVersionResolver();
        $permissionBitsResolver = new PermissionBitsResolver();
        $profile = $resolver->resolve(1.6, EncryptionAlgorithm::AES_128);

        self::assertSame(-4, $permissionBitsResolver->resolve(EncryptionPermissions::all(), $profile));
    }
}
