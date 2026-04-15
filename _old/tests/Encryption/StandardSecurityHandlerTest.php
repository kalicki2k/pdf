<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\Algorithm;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\Permissions;
use Kalle\Pdf\Encryption\StandardSecurityHandler;
use PHPUnit\Framework\TestCase;

final class StandardSecurityHandlerTest extends TestCase
{
    public function testItBuildsRevision3SecurityHandlerDataMatchingReferenceValues(): void
    {
        $data = new StandardSecurityHandler()->build(
            Encryption::rc4_128('user', 'owner'),
            new EncryptionProfile(Algorithm::RC4_128, 128, 2, 3),
            '10f7050476a6456a2e4f2b5b47297adf',
        );

        self::assertSame(-4, $data->permissionBits);
        self::assertSame(
            '0BA3835F88F90388E74E54584125CE142BE0DE24C6B0D37746E075B891756671',
            strtoupper(bin2hex($data->ownerValue)),
        );
        self::assertSame(32, strlen($data->userValue));
        self::assertSame(16, strlen($data->encryptionKey));
    }

    public function testItBuildsRevision4SecurityHandlerDataForAes128(): void
    {
        $data = new StandardSecurityHandler()->build(
            Encryption::aes128('user', 'owner')->withPermissions(
                new Permissions(print: false, modify: true, copy: false, annotate: true),
            ),
            new EncryptionProfile(Algorithm::AES_128, 128, 4, 4),
            '10f7050476a6456a2e4f2b5b47297adf',
        );

        self::assertSame(-24, $data->permissionBits);
        self::assertSame(
            '0BA3835F88F90388E74E54584125CE142BE0DE24C6B0D37746E075B891756671',
            strtoupper(bin2hex($data->ownerValue)),
        );
        self::assertSame(32, strlen($data->userValue));
        self::assertSame(16, strlen($data->encryptionKey));
    }

    public function testItBuildsRevision5SecurityHandlerDataForAes256(): void
    {
        $data = new StandardSecurityHandler(
            randomBytesGenerator: static fn (int $length): string => str_repeat(chr($length & 0xFF), $length),
        )->build(
            Encryption::aes256('user', 'owner')->withPermissions(Permissions::all()),
            new EncryptionProfile(Algorithm::AES_256, 256, 5, 5),
            '10f7050476a6456a2e4f2b5b47297adf',
        );

        self::assertSame(-4, $data->permissionBits);
        self::assertSame(48, strlen($data->ownerValue));
        self::assertSame(48, strlen($data->userValue));
        self::assertSame(32, strlen($data->encryptionKey));
        self::assertSame(32, strlen((string) $data->ownerEncryptionKey));
        self::assertSame(32, strlen((string) $data->userEncryptionKey));
        self::assertSame(16, strlen((string) $data->permsValue));
    }
}
