<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\Algorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\PermissionBitsResolver;
use Kalle\Pdf\Encryption\Permissions;
use PHPUnit\Framework\TestCase;

final class PermissionBitsResolverTest extends TestCase
{
    public function testItResolvesReadOnlyPermissionBits(): void
    {
        $bits = (new PermissionBitsResolver())->resolve(
            Permissions::readOnly(),
            new EncryptionProfile(Algorithm::RC4_128, 128, 2, 3),
        );

        self::assertSame(-64, $bits);
    }

    public function testItResolvesSelectedPermissionBits(): void
    {
        $bits = (new PermissionBitsResolver())->resolve(
            new Permissions(print: false, modify: true, copy: false, annotate: true),
            new EncryptionProfile(Algorithm::AES_128, 128, 4, 4),
        );

        self::assertSame(-24, $bits);
    }
}
