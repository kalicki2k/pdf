<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StandardSecurityHandlerDataTest extends TestCase
{
    #[Test]
    public function it_stores_all_security_handler_data_fields(): void
    {
        $data = new StandardSecurityHandlerData(
            ownerValue: 'owner',
            userValue: 'user',
            encryptionKey: 'key',
            permissionBits: -4,
            ownerEncryptionKey: 'oek',
            userEncryptionKey: 'uek',
            permsValue: 'perms',
        );

        self::assertSame('owner', $data->ownerValue);
        self::assertSame('user', $data->userValue);
        self::assertSame('key', $data->encryptionKey);
        self::assertSame(-4, $data->permissionBits);
        self::assertSame('oek', $data->ownerEncryptionKey);
        self::assertSame('uek', $data->userEncryptionKey);
        self::assertSame('perms', $data->permsValue);
    }
}
