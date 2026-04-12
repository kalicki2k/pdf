<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use Kalle\Pdf\Document\Signature\OpenSslPemSigningCredentials;
use Kalle\Pdf\Document\Signature\PdfSignatureOptions;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Writer\StringOutput;

use function openssl_csr_new;

use function openssl_csr_sign;

use const OPENSSL_KEYTYPE_RSA;

use function openssl_pkey_export;
use function openssl_pkey_new;
use function openssl_x509_export;

use PHPUnit\Framework\TestCase;

final class PdfTest extends TestCase
{
    public function testItRendersADocumentToAProvidedOutput(): void
    {
        $document = Pdf::document()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->build();
        $output = new StringOutput();

        Pdf::render($document, $output);

        self::assertStringStartsWith('%PDF-1.4', $output->contents());
        self::assertStringContainsString('/Title (Example Title)', $output->contents());
    }

    public function testItReturnsDocumentContentsAsAString(): void
    {
        $document = Pdf::document()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->build();

        $contents = Pdf::contents($document);

        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('%%EOF', $contents);
    }

    public function testItWritesADocumentToAPath(): void
    {
        $document = Pdf::document()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->build();
        $path = tempnam(sys_get_temp_dir(), 'pdf2-pdf-facade-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary path for the Pdf facade save test.');
        }

        unlink($path);
        $path .= '.pdf';

        Pdf::writeToFile($document, $path);
        self::assertFileExists($path);

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('%%EOF', $contents);

        unlink($path);
    }

    public function testItWritesADocumentToAStream(): void
    {
        $document = Pdf::document()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->build();
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to open a temporary stream for the Pdf facade write test.');
        }

        Pdf::writeToStream($document, $stream);

        rewind($stream);
        $contents = stream_get_contents($stream);

        self::assertIsString($contents);
        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('%%EOF', $contents);

        fclose($stream);
    }

    public function testItReturnsSignedDocumentContentsAsAString(): void
    {
        $document = Pdf::document()
            ->text('Signed')
            ->signatureField('approval_signature', 40, 500, 140, 28, 'Approval signature')
            ->build();

        $contents = Pdf::signedContents(
            $document,
            $this->testSigningCredentials(),
            new PdfSignatureOptions(fieldName: 'approval_signature', signerName: 'QA Signer'),
        );

        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('/FT /Sig', $contents);
        self::assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $contents);
    }

    public function testItMeasuresTextWidthThroughTheFacade(): void
    {
        self::assertEqualsWithDelta(22.74, Pdf::measureTextWidth('Hello', 10, StandardFont::HELVETICA), 0.0001);
    }

    public function testItMeasuresSymbolTextWidthThroughTheFacade(): void
    {
        self::assertSame(23.59, Pdf::measureTextWidth('αβγΩ', 10, StandardFont::SYMBOL));
    }

    public function testItMeasuresKerningAwareTextWidthThroughTheFacade(): void
    {
        self::assertEqualsWithDelta(12.63, Pdf::measureTextWidth('AV', 10, StandardFont::HELVETICA), 0.0001);
    }

    private function testSigningCredentials(): OpenSslPemSigningCredentials
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ]);

        self::assertNotFalse($privateKey);

        $csr = openssl_csr_new(
            ['commonName' => 'PDF2 Facade Signer'],
            $privateKey,
            ['digest_alg' => 'sha256'],
        );

        self::assertNotFalse($csr);

        $certificate = openssl_csr_sign(
            $csr,
            null,
            $privateKey,
            1,
            ['digest_alg' => 'sha256'],
        );

        self::assertNotFalse($certificate);
        self::assertTrue(openssl_x509_export($certificate, $certificatePem));
        self::assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));

        return new OpenSslPemSigningCredentials($certificatePem, $privateKeyPem);
    }
}
