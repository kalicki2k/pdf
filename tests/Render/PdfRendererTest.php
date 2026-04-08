<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Render\PdfRenderer;
use Kalle\Pdf\Render\StreamPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfRendererTest extends TestCase
{
    #[Test]
    public function it_writes_cross_reference_entries_with_the_actual_object_offsets(): void
    {
        $document = new Document(
            profile: Profile::standard(1.0),
            title: 'Minimal',
        );
        $renderer = new PdfRenderer();

        $output = $renderer->render($document);

        $catalogOffset = strpos($output, "1 0 obj\n");
        $pagesOffset = strpos($output, "2 0 obj\n");
        $infoOffset = strpos($output, "3 0 obj\n");
        $xrefOffset = strpos($output, "xref\n");

        self::assertNotFalse($catalogOffset);
        self::assertNotFalse($pagesOffset);
        self::assertNotFalse($infoOffset);
        self::assertNotFalse($xrefOffset);

        self::assertStringContainsString(sprintf("%010d 00000 n \n", $catalogOffset), $output);
        self::assertStringContainsString(sprintf("%010d 00000 n \n", $pagesOffset), $output);
        self::assertStringContainsString(sprintf("%010d 00000 n \n", $infoOffset), $output);
        self::assertStringContainsString("startxref\n{$xrefOffset}\n%%EOF", $output);
    }

    #[Test]
    public function it_writes_a_binary_comment_after_the_pdf_header(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $renderer = new PdfRenderer();

        $output = $renderer->render($document);

        self::assertStringStartsWith("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n", $output);
    }

    #[Test]
    public function it_writes_the_pdf_2_0_header_for_pdf_a_4_documents(): void
    {
        $document = new Document(profile: Profile::pdfA4());
        $renderer = new PdfRenderer();

        $output = $renderer->render($document);

        self::assertStringStartsWith("%PDF-2.0\n%\xE2\xE3\xCF\xD3\n", $output);
        self::assertStringNotContainsString('/Info 3 0 R', $output);
    }

    #[Test]
    public function it_marks_missing_object_ids_as_free_entries_in_the_cross_reference_table(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->getUniqObjectId();
        $document->registerFont('Helvetica');

        $renderer = new PdfRenderer();
        $output = $renderer->render($document);
        $catalogOffset = strpos($output, "1 0 obj\n");
        $pagesOffset = strpos($output, "2 0 obj\n");
        $infoOffset = strpos($output, "3 0 obj\n");
        $fontOffset = strpos($output, "5 0 obj\n");

        $metadataOffset = strpos($output, "6 0 obj\n");

        self::assertStringContainsString("xref\n0 7\n", $output);
        self::assertNotFalse($catalogOffset);
        self::assertNotFalse($pagesOffset);
        self::assertNotFalse($infoOffset);
        self::assertNotFalse($fontOffset);
        self::assertNotFalse($metadataOffset);
        self::assertMatchesRegularExpression(
            '/xref\n0 7\n'
            . '0000000000 65535 f \n'
            . sprintf('%010d', $catalogOffset) . ' 00000 n \n'
            . sprintf('%010d', $pagesOffset) . ' 00000 n \n'
            . sprintf('%010d', $infoOffset) . ' 00000 n \n'
            . '0000000000 65535 f \n'
            . sprintf('%010d', $fontOffset) . ' 00000 n \n'
            . sprintf('%010d', $metadataOffset) . ' 00000 n \n/',
            $output,
        );
        self::assertStringContainsString("trailer\n<< /Size 7\n/Root 1 0 R\n/Info 3 0 R\n", $output);
        self::assertMatchesRegularExpression('/\/ID \[<[^>]+> <[^>]+>]/', $output);
    }

    #[Test]
    public function it_can_write_pdf_bytes_to_a_stream_output(): void
    {
        $document = new Document(
            profile: Profile::standard(1.4),
            title: 'Minimal',
        );
        $renderer = new PdfRenderer();
        $expectedOutput = $renderer->render($document);
        $stream = fopen('php://temp', 'w+b');

        self::assertNotFalse($stream);

        $renderer->write($document, new StreamPdfOutput($stream));
        rewind($stream);

        $writtenOutput = stream_get_contents($stream);

        fclose($stream);

        self::assertSame($expectedOutput, $writtenOutput);
    }
}
