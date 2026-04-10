<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Render;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfFileStructure;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\PdfRenderer;
use Kalle\Pdf\Render\PdfSerializationPlan;
use Kalle\Pdf\Render\PdfTrailer;
use Kalle\Pdf\Render\StreamPdfOutput;

use function Kalle\Pdf\Tests\Support\writePlanToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class PdfRendererTest extends TestCase
{
    #[Test]
    public function it_writes_cross_reference_entries_with_the_actual_object_offsets(): void
    {
        $renderer = new PdfRenderer();
        $plan = $this->serializationPlan(version: 1.0);

        $output = writePlanToString($renderer, $plan);

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
        $renderer = new PdfRenderer();
        $plan = $this->serializationPlan(version: 1.4);

        $output = writePlanToString($renderer, $plan);

        self::assertStringStartsWith("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n", $output);
    }

    #[Test]
    public function it_writes_the_pdf_2_0_header_for_pdf_a_4_documents(): void
    {
        $renderer = new PdfRenderer();
        $plan = $this->serializationPlan(version: 2.0, infoObjectId: null);

        $output = writePlanToString($renderer, $plan);

        self::assertStringStartsWith("%PDF-2.0\n%\xE2\xE3\xCF\xD3\n", $output);
        self::assertStringNotContainsString('/Info 3 0 R', $output);
    }

    #[Test]
    public function it_marks_missing_object_ids_as_free_entries_in_the_cross_reference_table(): void
    {
        $renderer = new PdfRenderer();
        $plan = $this->serializationPlan(
            objects: [
                $this->indirectObject(1, '<< /Type /Catalog >>'),
                $this->indirectObject(2, '<< /Type /Pages /Count 0 >>'),
                $this->indirectObject(3, '<< /Producer (kalle/pdf) >>'),
                $this->indirectObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'),
                $this->indirectObject(6, "<< /Type /Metadata /Subtype /XML /Length 0 >>\nstream\n\nendstream"),
            ],
            version: 1.4,
        );
        $output = writePlanToString($renderer, $plan);
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
        $renderer = new PdfRenderer();
        $plan = $this->serializationPlan(version: 1.4);
        $expectedOutput = writePlanToString($renderer, $plan);
        $stream = fopen('php://temp', 'w+b');

        self::assertNotFalse($stream);

        $renderer->write($plan, new StreamPdfOutput($stream));
        rewind($stream);

        $writtenOutput = stream_get_contents($stream);

        fclose($stream);

        self::assertSame($expectedOutput, $writtenOutput);
    }

    /**
     * @param list<IndirectObject>|null $objects
     */
    private function serializationPlan(?array $objects = null, float $version = 1.4, ?int $infoObjectId = 3): PdfSerializationPlan
    {
        return new PdfSerializationPlan(
            objects: $objects ?? [
                $this->indirectObject(1, '<< /Type /Catalog /Pages 2 0 R >>'),
                $this->indirectObject(2, '<< /Type /Pages /Count 0 >>'),
                $this->indirectObject(3, '<< /Producer (kalle/pdf) >>'),
            ],
            fileStructure: new PdfFileStructure(
                version: $version,
                trailer: new PdfTrailer(
                    rootObjectId: 1,
                    infoObjectId: $infoObjectId,
                    encryptObjectId: null,
                    documentId: ['0123456789abcdef0123456789abcdef', '0123456789abcdef0123456789abcdef'],
                ),
            ),
        );
    }

    private function indirectObject(int $id, string $body): IndirectObject
    {
        return new class ($id, $body) extends IndirectObject {
            public function __construct(int $id, private readonly string $body)
            {
                parent::__construct($id);
            }

            protected function writeObject(PdfOutput $output): void
            {
                $output->write($this->id . " 0 obj\n" . $this->body . "\nendobj\n");
            }
        };
    }
}
