<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionPermissions;
use Kalle\Pdf\Encryption\EncryptionVersionResolver;
use Kalle\Pdf\Encryption\PermissionBitsResolver;
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
    public function it_resolves_and_stores_the_encryption_profile_via_encrypt_options(): void
    {
        $document = new Document(version: 1.6);
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
        $document = new Document(version: 1.4);
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
