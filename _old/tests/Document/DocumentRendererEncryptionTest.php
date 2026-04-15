<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\Permissions;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Text\TextOptions;
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
            ->profile(Profile::pdf16())
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
            ->profile(Profile::pdf17())
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
            ->profile(Profile::pdf16())
            ->encryption(Encryption::aes128('user', 'owner')->withPermissions(
                new Permissions(print: false, modify: true, copy: false, annotate: true),
            ))
            ->contents();

        self::assertStringContainsString('/P -24', $pdf);
    }

    public function testItEncryptsComplexPdfContentWithEmbeddedFontImageAndTable(): void
    {
        $fontPath = dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';
        $table = Table::define(
            TableColumn::fixed(110),
            TableColumn::proportional(1),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withCaption(TableCaption::text('Quarterly Secret Table'))
                    ->withCellPadding(CellPadding::all(6)),
            )
            ->withHeaderRows(
                TableRow::fromCells(
                    TableCell::text('Region')->withHeaderScope(TableHeaderScope::COLUMN),
                    TableCell::text('Notes')->withHeaderScope(TableHeaderScope::COLUMN),
                ),
            )
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('North')->withHeaderScope(TableHeaderScope::ROW),
                    TableCell::text('Pipeline Secret'),
                ),
            );

        $pdf = DefaultDocumentBuilder::make()
            ->profile(Profile::pdf17())
            ->title('Complex Secret Title')
            ->author('Complex Secret Author')
            ->encryption(Encryption::aes256('user', 'owner'))
            ->text('Visible Intro Secret', TextOptions::make(
                embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            ))
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::absolute(left: 40, bottom: 620, width: 120),
            )
            ->table($table)
            ->contents();

        self::assertStringContainsString('/Encrypt ', $pdf);
        self::assertStringContainsString('/CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>', $pdf);
        self::assertStringNotContainsString('Complex Secret Title', $pdf);
        self::assertStringNotContainsString('Complex Secret Author', $pdf);
        self::assertStringNotContainsString('Visible Intro Secret', $pdf);
        self::assertStringNotContainsString('Quarterly Secret Table', $pdf);
        self::assertStringNotContainsString('Pipeline Secret', $pdf);
        self::assertStringNotContainsString('jpeg-bytes', $pdf);
    }
}
