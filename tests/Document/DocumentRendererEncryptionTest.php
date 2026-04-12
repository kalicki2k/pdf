<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Encryption\Encryption;
use PHPUnit\Framework\TestCase;

final class DocumentRendererEncryptionTest extends TestCase
{
    public function testItDoesNotLeavePlaintextStringsInAnEncryptedPdf(): void
    {
        $pdf = DefaultDocumentBuilder::make()
            ->title('Secret Title')
            ->author('Secret Author')
            ->encryption(Encryption::rc4_128('user', 'owner'))
            ->text('Visible Secret')
            ->contents();

        self::assertStringContainsString('/Encrypt ', $pdf);
        self::assertMatchesRegularExpression('/\/ID \[<[0-9a-f]{32}> <[0-9a-f]{32}>\]/i', $pdf);
        self::assertStringNotContainsString('Secret Title', $pdf);
        self::assertStringNotContainsString('Secret Author', $pdf);
        self::assertStringNotContainsString('Visible Secret', $pdf);
    }

    public function testItDoesNotLeavePlaintextStringsInAnAes128EncryptedPdf(): void
    {
        $pdf = DefaultDocumentBuilder::make()
            ->profile(\Kalle\Pdf\Document\Profile::pdf16())
            ->title('AES Secret Title')
            ->author('AES Secret Author')
            ->encryption(Encryption::aes128('user', 'owner'))
            ->text('Visible AES Secret')
            ->contents();

        self::assertStringContainsString('/Encrypt ', $pdf);
        self::assertStringContainsString('/CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>', $pdf);
        self::assertStringNotContainsString('AES Secret Title', $pdf);
        self::assertStringNotContainsString('AES Secret Author', $pdf);
        self::assertStringNotContainsString('Visible AES Secret', $pdf);
    }
}
