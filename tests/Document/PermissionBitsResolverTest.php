<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Profile\PermissionBitsResolver;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Security\EncryptionPermissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PermissionBitsResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_permission_bits_for_a_supported_revision(): void
    {
        $resolver = new PermissionBitsResolver();
        $profile = new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3);

        self::assertSame(-4, $resolver->resolve(EncryptionPermissions::readOnly(), $profile));
    }

    #[Test]
    public function it_rejects_unsupported_revisions(): void
    {
        $resolver = new PermissionBitsResolver();
        $profile = new EncryptionProfile(EncryptionAlgorithm::RC4_40, 40, 1, 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported encryption revision for permission resolution.');

        $resolver->resolve(EncryptionPermissions::readOnly(), $profile);
    }
}
