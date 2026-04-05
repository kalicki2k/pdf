<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionPermissions;
use Kalle\Pdf\Encryption\EncryptionVersionResolver;
use Kalle\Pdf\Encryption\StandardSecurityHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StandardSecurityHandlerTest extends TestCase
{
    #[Test]
    public function it_matches_qpdf_reference_values_for_rc4_128(): void
    {
        $profile = (new EncryptionVersionResolver())->resolve(1.4, EncryptionAlgorithm::RC4_128);
        $options = new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            permissions: EncryptionPermissions::readOnly(),
            algorithm: EncryptionAlgorithm::RC4_128,
        );

        $data = (new StandardSecurityHandler())->build(
            $options,
            $profile,
            '10f7050476a6456a2e4f2b5b47297adf',
        );

        self::assertSame(
            '0BA3835F88F90388E74E54584125CE142BE0DE24C6B0D37746E075B891756671',
            strtoupper(bin2hex($data->ownerValue)),
        );
        self::assertSame(
            '8A9D3F1A5615CADC2AD44693DF7F23C5',
            strtoupper(bin2hex(substr($data->userValue, 0, 16))),
        );
        self::assertSame(-4, $data->permissionBits);
        self::assertSame(16, strlen($data->encryptionKey));
    }
}
