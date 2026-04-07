<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncryptionProfileTest extends TestCase
{
    #[Test]
    public function it_stores_encryption_profile_values(): void
    {
        $profile = new EncryptionProfile(
            EncryptionAlgorithm::AES_256,
            256,
            5,
            5,
        );

        self::assertSame(EncryptionAlgorithm::AES_256, $profile->algorithm);
        self::assertSame(256, $profile->keyLengthInBits);
        self::assertSame(5, $profile->dictionaryVersion);
        self::assertSame(5, $profile->revision);
    }
}
