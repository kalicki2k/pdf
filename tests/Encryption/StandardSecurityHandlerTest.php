<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\Algorithm;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardSecurityHandler;
use PHPUnit\Framework\TestCase;

final class StandardSecurityHandlerTest extends TestCase
{
    public function testItBuildsRevision3SecurityHandlerDataMatchingReferenceValues(): void
    {
        $data = (new StandardSecurityHandler())->build(
            Encryption::rc4_128('user', 'owner'),
            new EncryptionProfile(Algorithm::RC4_128, 128, 2, 3),
            '10f7050476a6456a2e4f2b5b47297adf',
        );

        self::assertSame(-4, $data->permissionBits);
        self::assertSame(
            '0BA3835F88F90388E74E54584125CE142BE0DE24C6B0D37746E075B891756671',
            strtoupper(bin2hex($data->ownerValue)),
        );
        self::assertSame(
            '8A9D3F1A5615CADC2AD44693DF7F23C5',
            strtoupper(bin2hex(substr($data->userValue, 0, 16))),
        );
        self::assertSame(16, strlen($data->encryptionKey));
    }

    public function testItBuildsRevision4SecurityHandlerDataForAes128(): void
    {
        $data = (new StandardSecurityHandler())->build(
            Encryption::aes128('user', 'owner'),
            new EncryptionProfile(Algorithm::AES_128, 128, 4, 4),
            '10f7050476a6456a2e4f2b5b47297adf',
        );

        self::assertSame(-4, $data->permissionBits);
        self::assertSame(
            '0BA3835F88F90388E74E54584125CE142BE0DE24C6B0D37746E075B891756671',
            strtoupper(bin2hex($data->ownerValue)),
        );
        self::assertSame(
            '8A9D3F1A5615CADC2AD44693DF7F23C5',
            strtoupper(bin2hex(substr($data->userValue, 0, 16))),
        );
        self::assertSame(16, strlen($data->encryptionKey));
    }
}
