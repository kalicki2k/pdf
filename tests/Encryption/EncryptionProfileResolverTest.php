<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use InvalidArgumentException;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Encryption\Algorithm;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\EncryptionProfileResolver;
use PHPUnit\Framework\TestCase;

final class EncryptionProfileResolverTest extends TestCase
{
    public function testItResolvesRc4128ForSupportedProfiles(): void
    {
        $profile = (new EncryptionProfileResolver())->resolve(
            Profile::pdf14(),
            Encryption::rc4_128('user', 'owner'),
        );

        self::assertSame(Algorithm::RC4_128, $profile->algorithm);
        self::assertSame(128, $profile->keyLengthInBits);
        self::assertSame(2, $profile->dictionaryVersion);
        self::assertSame(3, $profile->revision);
    }

    public function testItRejectsEncryptionForPdfAProfiles(): void
    {
        $resolver = new EncryptionProfileResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow encryption.');

        $resolver->resolve(Profile::pdfA2u(), Encryption::rc4_128('user', 'owner'));
    }

    public function testItResolvesAes128ForSupportedProfiles(): void
    {
        $profile = (new EncryptionProfileResolver())->resolve(
            Profile::pdf16(),
            Encryption::aes128('user', 'owner'),
        );

        self::assertSame(Algorithm::AES_128, $profile->algorithm);
        self::assertSame(128, $profile->keyLengthInBits);
        self::assertSame(4, $profile->dictionaryVersion);
        self::assertSame(4, $profile->revision);
    }

    public function testItRejectsRc4128ForTooOldPdfVersions(): void
    {
        $resolver = new EncryptionProfileResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RC4 128-bit encryption requires PDF 1.4 or newer.');

        $resolver->resolve(Profile::pdf13(), Encryption::rc4_128('user', 'owner'));
    }

    public function testItRejectsAes128ForTooOldPdfVersions(): void
    {
        $resolver = new EncryptionProfileResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AES 128-bit encryption requires PDF 1.6 or newer.');

        $resolver->resolve(Profile::pdf15(), Encryption::aes128('user', 'owner'));
    }

    public function testItResolvesAes256ForSupportedProfiles(): void
    {
        $profile = (new EncryptionProfileResolver())->resolve(
            Profile::pdf17(),
            Encryption::aes256('user', 'owner'),
        );

        self::assertSame(Algorithm::AES_256, $profile->algorithm);
        self::assertSame(256, $profile->keyLengthInBits);
        self::assertSame(5, $profile->dictionaryVersion);
        self::assertSame(5, $profile->revision);
    }

    public function testItRejectsAes256ForTooOldPdfVersions(): void
    {
        $resolver = new EncryptionProfileResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AES 256-bit encryption requires PDF 1.7 or newer.');

        $resolver->resolve(Profile::pdf16(), Encryption::aes256('user', 'owner'));
    }
}
