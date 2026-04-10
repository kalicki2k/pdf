<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\Crypto\Rc4Cipher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Rc4CipherTest extends TestCase
{
    #[Test]
    public function it_encrypts_with_a_known_rc4_test_vector(): void
    {
        $cipher = new Rc4Cipher();

        self::assertSame(
            'bbf316e8d940af0ad3',
            bin2hex($cipher->encrypt('Key', 'Plaintext')),
        );
    }

    #[Test]
    public function it_decrypts_by_reapplying_the_same_keystream(): void
    {
        $cipher = new Rc4Cipher();
        $encrypted = $cipher->encrypt('Secret', 'hello world');

        self::assertSame('hello world', $cipher->encrypt('Secret', $encrypted));
    }
}
