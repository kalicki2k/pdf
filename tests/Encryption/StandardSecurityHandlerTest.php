<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

require_once __DIR__ . '/Support/StandardObjectEncryptorOpenSslStub.php';

use InvalidArgumentException;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionPermissions;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\EncryptionVersionResolver;

use function Kalle\Pdf\Encryption\setStandardSecurityHandlerOpenSslShouldFail;

use Kalle\Pdf\Encryption\StandardSecurityHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class StandardSecurityHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        setStandardSecurityHandlerOpenSslShouldFail(false);
    }

    #[Test]
    public function it_rejects_unsupported_standard_security_handler_revisions(): void
    {
        $handler = new StandardSecurityHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only standard security handler revisions 2, 3, 4 and 5 are supported in this stage.');

        $handler->build(
            new EncryptionOptions(userPassword: 'user', ownerPassword: 'owner'),
            new EncryptionProfile(EncryptionAlgorithm::AUTO, 0, 0, 1),
            '10f7050476a6456a2e4f2b5b47297adf',
        );
    }

    #[Test]
    public function it_builds_revision_2_security_handler_data_with_exactly_32_byte_passwords(): void
    {
        $profile = new EncryptionProfile(EncryptionAlgorithm::RC4_40, 40, 1, 2);
        $options = new EncryptionOptions(
            userPassword: '12345678901234567890123456789012',
            ownerPassword: 'abcdefghijklmnopqrstuvwxyzABCDEF',
            permissions: EncryptionPermissions::readOnly(),
            algorithm: EncryptionAlgorithm::RC4_40,
        );

        $data = (new StandardSecurityHandler())->build(
            $options,
            $profile,
            '10f7050476a6456a2e4f2b5b47297adf',
        );

        self::assertSame(-4, $data->permissionBits);
        self::assertSame(5, strlen($data->encryptionKey));
        self::assertSame(32, strlen($data->ownerValue));
        self::assertSame(32, strlen($data->userValue));
    }

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

    #[Test]
    public function it_rejects_revision_5_cbc_encryption_when_openssl_fails(): void
    {
        setStandardSecurityHandlerOpenSslShouldFail(true);

        $method = new ReflectionMethod(StandardSecurityHandler::class, 'encryptAes256CbcNoPadding');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to encrypt AES-256-CBC security handler payload.');

        $method->invoke(new StandardSecurityHandler(), str_repeat('A', 32), str_repeat('B', 32));
    }

    #[Test]
    public function it_rejects_revision_5_ecb_encryption_when_openssl_fails(): void
    {
        setStandardSecurityHandlerOpenSslShouldFail(true);

        $method = new ReflectionMethod(StandardSecurityHandler::class, 'encryptAes256EcbNoPadding');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to encrypt AES-256-ECB security handler payload.');

        $method->invoke(new StandardSecurityHandler(), str_repeat('A', 16), str_repeat('B', 32));
    }
}
