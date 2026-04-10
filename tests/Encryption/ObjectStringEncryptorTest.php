<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ObjectStringEncryptorTest extends TestCase
{
    #[Test]
    public function it_encrypts_strings_for_a_bound_object_id(): void
    {
        $encryptor = new ObjectStringEncryptor(
            new StandardObjectEncryptor(
                new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ),
            7,
        );

        self::assertSame(
            (new StandardObjectEncryptor(
                new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                new StandardSecurityHandlerData('', '', '1234567890123456', -4),
            ))->encryptString(7, 'Hello'),
            $encryptor->encrypt('Hello'),
        );
    }
}
