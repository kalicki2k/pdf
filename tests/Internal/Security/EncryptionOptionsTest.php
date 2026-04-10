<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Security;

use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Internal\Security\EncryptionOptions;
use Kalle\Pdf\Internal\Security\EncryptionPermissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncryptionOptionsTest extends TestCase
{
    #[Test]
    public function it_stores_encryption_option_values(): void
    {
        $permissions = EncryptionPermissions::readOnly();
        $options = new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            permissions: $permissions,
            algorithm: EncryptionAlgorithm::AES_256,
        );

        self::assertSame('user', $options->userPassword);
        self::assertSame('owner', $options->ownerPassword);
        self::assertSame($permissions, $options->permissions);
        self::assertSame(EncryptionAlgorithm::AES_256, $options->algorithm);
    }
}
