<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\Algorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\ObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use PHPUnit\Framework\TestCase;

final class ObjectEncryptorTest extends TestCase
{
    public function testItEncryptsAndDecryptsLiteralStringsInsideObjects(): void
    {
        $encryptor = new ObjectEncryptor(
            new EncryptionProfile(Algorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );

        $plainObject = '<< /Title (Hello \(World\)) /Subject (Line\nBreak) >>';
        $encryptedObject = $encryptor->encryptObject($plainObject, 7);
        $decryptedObject = $encryptor->encryptObject($encryptedObject, 7);

        self::assertNotSame($plainObject, $encryptedObject);
        self::assertStringNotContainsString('Hello', $encryptedObject);
        self::assertSame('<< /Title (Hello \(World\)) /Subject (Line\012Break) >>', $decryptedObject);
    }

    public function testItEncryptsAndDecryptsStreamPayloads(): void
    {
        $encryptor = new ObjectEncryptor(
            new EncryptionProfile(Algorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );

        $plainObject = "<< /Length 11 >>\nstream\nHello World\nendstream";
        $encryptedObject = $encryptor->encryptObject($plainObject, 9);
        $decryptedObject = $encryptor->encryptObject($encryptedObject, 9);

        self::assertStringContainsString("stream\n", $encryptedObject);
        self::assertStringContainsString("\nendstream", $encryptedObject);
        self::assertStringNotContainsString('Hello World', $encryptedObject);
        self::assertSame($plainObject, $decryptedObject);
    }
}
