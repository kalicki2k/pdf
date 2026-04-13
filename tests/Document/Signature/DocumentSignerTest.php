<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Signature;

use const OPENSSL_CMS_BINARY;
use const OPENSSL_CMS_DETACHED;
use const OPENSSL_CMS_NOVERIFY;
use const OPENSSL_ENCODING_DER;
use const OPENSSL_KEYTYPE_RSA;

use function file_put_contents;
use function openssl_cms_verify;
use function openssl_csr_new;
use function openssl_csr_sign;
use function openssl_pkey_export;
use function openssl_pkey_new;
use function openssl_x509_export;
use function preg_match;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use InvalidArgumentException;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Signature\DocumentSigner;
use Kalle\Pdf\Document\Signature\OpenSslPemSigningCredentials;
use Kalle\Pdf\Document\Signature\PdfSignatureOptions;
use Kalle\Pdf\Encryption\Encryption;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use OpenSSLCertificateSigningRequest;
use PHPUnit\Framework\TestCase;

final class DocumentSignerTest extends TestCase
{
    public function testItSignsADocumentWithDetachedCmsContents(): void
    {
        $credentials = $this->testCredentials();
        $document = DefaultDocumentBuilder::make()
            ->text('Approval')
            ->signatureField('approval_signature', 40, 500, 140, 28, 'Approval signature')
            ->build();

        $signedPdf = new DocumentSigner()->contents(
            $document,
            $credentials,
            new PdfSignatureOptions(
                fieldName: 'approval_signature',
                signerName: 'QA Signer',
                reason: 'Approval',
                location: 'Berlin',
                contactInfo: 'qa@example.com',
            ),
        );

        self::assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $signedPdf);
        self::assertStringContainsString('/FT /Sig', $signedPdf);
        self::assertMatchesRegularExpression('/\/V \d+ 0 R/', $signedPdf);
        self::assertMatchesRegularExpression('/\/ByteRange \[\d{20} \d{20} \d{20} \d{20}\]/', $signedPdf);
        self::assertSame(0, preg_match(
            '/\/ByteRange \[0{20} 0{20} 0{20} 0{20}\]/',
            $signedPdf,
        ));
        self::assertTrue($this->verifyDetachedSignature($signedPdf));
    }

    public function testItRejectsMissingSignatureFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document does not contain an unsigned signature field named "approval_signature".');

        new DocumentSigner()->contents(
            DefaultDocumentBuilder::make()->text('Unsigned')->build(),
            $this->testCredentials(),
            new PdfSignatureOptions(fieldName: 'approval_signature'),
        );
    }

    public function testItRejectsEncryptedDocuments(): void
    {
        $unsignedDocument = DefaultDocumentBuilder::make()
            ->signatureField('approval_signature', 40, 500, 140, 28)
            ->build()
        ;
        $document = new Document(
            profile: $unsignedDocument->profile,
            pages: $unsignedDocument->pages,
            title: $unsignedDocument->title,
            author: $unsignedDocument->author,
            subject: $unsignedDocument->subject,
            language: $unsignedDocument->language,
            creator: $unsignedDocument->creator,
            creatorTool: $unsignedDocument->creatorTool,
            pdfaOutputIntent: $unsignedDocument->pdfaOutputIntent,
            encryption: Encryption::aes128('user', 'owner'),
            taggedTables: $unsignedDocument->taggedTables,
            taggedTextBlocks: $unsignedDocument->taggedTextBlocks,
            attachments: $unsignedDocument->attachments,
            acroForm: $unsignedDocument->acroForm,
            taggedLists: $unsignedDocument->taggedLists,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cryptographic signing of encrypted documents is not supported.');

        new DocumentSigner()->contents(
            $document,
            $this->testCredentials(),
            new PdfSignatureOptions(fieldName: 'approval_signature'),
        );
    }

    private function testCredentials(): OpenSslPemSigningCredentials
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ]);

        self::assertNotFalse($privateKey);
        if ($privateKey === false) {
            self::fail('Expected OpenSSL private key generation to succeed.');
        }
        /** @var OpenSSLAsymmetricKey $privateKey */

        $csr = openssl_csr_new(
            ['commonName' => 'PDF2 Test Signer'],
            $privateKey,
            ['digest_alg' => 'sha256'],
        );

        self::assertNotFalse($csr);
        if ($csr === false) {
            self::fail('Expected OpenSSL CSR generation to succeed.');
        }
        /** @var OpenSSLCertificateSigningRequest $csr */

        $certificate = openssl_csr_sign(
            $csr,
            null,
            $privateKey,
            1,
            ['digest_alg' => 'sha256'],
        );

        self::assertNotFalse($certificate);
        if ($certificate === false) {
            self::fail('Expected OpenSSL certificate signing to succeed.');
        }
        /** @var OpenSSLCertificate $certificate */
        self::assertTrue(openssl_x509_export($certificate, $certificatePem));
        self::assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));
        self::assertIsString($certificatePem);
        self::assertIsString($privateKeyPem);

        return new OpenSslPemSigningCredentials($certificatePem, $privateKeyPem);
    }

    private function verifyDetachedSignature(string $signedPdf): bool
    {
        self::assertSame(1, preg_match('/\/ByteRange \[(\d+) (\d+) (\d+) (\d+)\]/', $signedPdf, $byteRangeMatch));
        self::assertSame(1, preg_match('/\/Contents <([0-9A-F]+)>/', $signedPdf, $contentsMatch));
        self::assertArrayHasKey(1, $byteRangeMatch);
        self::assertArrayHasKey(2, $byteRangeMatch);
        self::assertArrayHasKey(3, $byteRangeMatch);
        self::assertArrayHasKey(4, $byteRangeMatch);
        self::assertArrayHasKey(1, $contentsMatch);
        if (
            !isset(
                $byteRangeMatch[1],
                $byteRangeMatch[2],
                $byteRangeMatch[3],
                $byteRangeMatch[4],
                $contentsMatch[1],
            )
        ) {
            self::fail('Expected ByteRange and Contents matches to contain capture groups.');
        }

        $byteRange = [(int) $byteRangeMatch[1], (int) $byteRangeMatch[2], (int) $byteRangeMatch[3], (int) $byteRangeMatch[4]];
        $signedData = substr($signedPdf, $byteRange[0], $byteRange[1])
            . substr($signedPdf, $byteRange[2], $byteRange[3]);
        $signatureDer = $this->extractDerSignature($contentsMatch[1]);

        $contentPath = $this->temporaryPath('pdf2-verify-content-');
        $signaturePath = $this->temporaryPath('pdf2-verify-signature-');
        $certificatesPath = $this->temporaryPath('pdf2-verify-out-');

        try {
            file_put_contents($contentPath, $signedData);
            file_put_contents($signaturePath, $signatureDer);

            return openssl_cms_verify(
                $contentPath,
                OPENSSL_CMS_BINARY | OPENSSL_CMS_DETACHED | OPENSSL_CMS_NOVERIFY,
                $certificatesPath,
                [],
                null,
                null,
                null,
                $signaturePath,
                OPENSSL_ENCODING_DER,
            );
        } finally {
            unlink($contentPath);
            unlink($signaturePath);
            unlink($certificatesPath);
        }
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        self::assertNotFalse($path);

        return $path;
    }

    private function extractDerSignature(string $paddedHex): string
    {
        $binary = hex2bin($paddedHex);

        self::assertIsString($binary);
        self::assertGreaterThanOrEqual(4, strlen($binary));
        self::assertSame("\x30", $binary[0]);

        $lengthByte = ord($binary[1]);

        if (($lengthByte & 0x80) === 0) {
            $headerLength = 2;
            $contentLength = $lengthByte;
        } else {
            $lengthOctets = $lengthByte & 0x7F;

            self::assertGreaterThan(0, $lengthOctets);

            $headerLength = 2 + $lengthOctets;
            $contentLength = 0;

            for ($index = 0; $index < $lengthOctets; $index++) {
                $contentLength = ($contentLength << 8) | ord($binary[2 + $index]);
            }
        }

        return substr($binary, 0, $headerLength + $contentLength);
    }
}
