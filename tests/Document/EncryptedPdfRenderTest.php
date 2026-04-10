<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Security\EncryptionOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncryptedPdfRenderTest extends TestCase
{
    #[Test]
    public function it_does_not_leave_plaintext_strings_or_contents_in_an_rc4_encrypted_pdf(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            title: 'Secret Title',
            author: 'Secret Author',
        );
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            algorithm: EncryptionAlgorithm::RC4_128,
        ));
        $document->registerFont('Helvetica');

        $page = $document->addPage();
        $page->addText('Visible Secret', new Position(20, 20), 'Helvetica', 12);

        $pdf = $document->render();

        self::assertStringContainsString('/Encrypt ', $pdf);
        self::assertStringNotContainsString('Secret Title', $pdf);
        self::assertStringNotContainsString('Secret Author', $pdf);
        self::assertStringNotContainsString('Visible Secret', $pdf);
    }

    #[Test]
    public function it_produces_a_qpdf_readable_aes_128_encrypted_pdf(): void
    {
        $document = new Document(
            profile: Profile::standard(1.6),
            title: 'AES Secret Title',
            author: 'AES Secret Author',
        );
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            algorithm: EncryptionAlgorithm::AES_128,
        ));
        $document->registerFont('Helvetica');

        $page = $document->addPage();
        $page->addText('Visible AES Secret', new Position(20, 20), 'Helvetica', 12);

        $encryptedPdf = $document->render();

        self::assertStringNotContainsString('AES Secret Title', $encryptedPdf);
        self::assertStringNotContainsString('Visible AES Secret', $encryptedPdf);

        $inputPath = sys_get_temp_dir() . '/pdf-aes-in-' . uniqid('', true) . '.pdf';
        $outputPath = sys_get_temp_dir() . '/pdf-aes-out-' . uniqid('', true) . '.pdf';

        try {
            file_put_contents($inputPath, $encryptedPdf);
            chmod($inputPath, 0644);

            exec(
                sprintf(
                    'qpdf --password=%s --decrypt --qdf --stream-data=uncompress %s %s 2>&1',
                    escapeshellarg('user'),
                    escapeshellarg($inputPath),
                    escapeshellarg($outputPath),
                ),
                $output,
                $exitCode,
            );

            self::assertSame(0, $exitCode, implode(PHP_EOL, $output));

            $decryptedPdf = file_get_contents($outputPath);
            self::assertIsString($decryptedPdf);
            self::assertStringContainsString('AES Secret Title', $decryptedPdf);
            self::assertStringContainsString('Visible AES Secret', $decryptedPdf);
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }

    #[Test]
    public function it_produces_a_qpdf_readable_aes_256_encrypted_pdf(): void
    {
        $document = new Document(
            profile: Profile::standard(1.7),
            title: 'AES256 Secret Title',
            author: 'AES256 Secret Author',
        );
        $document->encrypt(new EncryptionOptions(
            userPassword: 'user',
            ownerPassword: 'owner',
            algorithm: EncryptionAlgorithm::AES_256,
        ));
        $document->registerFont('Helvetica');

        $page = $document->addPage();
        $page->addText('Visible AES256 Secret', new Position(20, 20), 'Helvetica', 12);

        $encryptedPdf = $document->render();

        self::assertStringNotContainsString('AES256 Secret Title', $encryptedPdf);
        self::assertStringNotContainsString('Visible AES256 Secret', $encryptedPdf);

        $inputPath = sys_get_temp_dir() . '/pdf-aes256-in-' . uniqid('', true) . '.pdf';
        $outputPath = sys_get_temp_dir() . '/pdf-aes256-out-' . uniqid('', true) . '.pdf';

        try {
            file_put_contents($inputPath, $encryptedPdf);
            chmod($inputPath, 0644);

            exec(
                sprintf(
                    'qpdf --password=%s --decrypt --qdf --stream-data=uncompress %s %s 2>&1',
                    escapeshellarg('user'),
                    escapeshellarg($inputPath),
                    escapeshellarg($outputPath),
                ),
                $output,
                $exitCode,
            );

            self::assertSame(0, $exitCode, implode(PHP_EOL, $output));

            $decryptedPdf = file_get_contents($outputPath);
            self::assertIsString($decryptedPdf);
            self::assertStringContainsString('AES256 Secret Title', $decryptedPdf);
            self::assertStringContainsString('Visible AES256 Secret', $decryptedPdf);
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }
}
