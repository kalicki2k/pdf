<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\Permissions;
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

    public function testItDoesNotLeavePlaintextStringsInAnAes256EncryptedPdf(): void
    {
        $pdf = DefaultDocumentBuilder::make()
            ->profile(\Kalle\Pdf\Document\Profile::pdf17())
            ->title('AES256 Secret Title')
            ->author('AES256 Secret Author')
            ->encryption(Encryption::aes256('user', 'owner'))
            ->text('Visible AES256 Secret')
            ->contents();

        self::assertStringContainsString('/Encrypt ', $pdf);
        self::assertStringContainsString('/CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>', $pdf);
        self::assertStringContainsString('/OE <', $pdf);
        self::assertStringContainsString('/UE <', $pdf);
        self::assertStringContainsString('/Perms <', $pdf);
        self::assertStringNotContainsString('AES256 Secret Title', $pdf);
        self::assertStringNotContainsString('AES256 Secret Author', $pdf);
        self::assertStringNotContainsString('Visible AES256 Secret', $pdf);
    }

    public function testItWritesConfiguredPermissionsIntoTheRenderedEncryptDictionary(): void
    {
        $pdf = DefaultDocumentBuilder::make()
            ->profile(\Kalle\Pdf\Document\Profile::pdf16())
            ->encryption(Encryption::aes128('user', 'owner')->withPermissions(
                new Permissions(print: false, modify: true, copy: false, annotate: true),
            ))
            ->contents();

        self::assertStringContainsString('/P -24', $pdf);
    }
}
