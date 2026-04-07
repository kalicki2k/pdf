<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncryptionAlgorithmTest extends TestCase
{
    #[Test]
    public function it_exposes_all_expected_encryption_algorithms(): void
    {
        self::assertSame([
            'AUTO',
            'RC4_40',
            'RC4_128',
            'AES_128',
            'AES_256',
        ], array_column(EncryptionAlgorithm::cases(), 'name'));
    }
}
