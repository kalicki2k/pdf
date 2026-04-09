<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfFileStructure;
use Kalle\Pdf\Render\PdfSerializationPlan;
use Kalle\Pdf\Render\PdfSerializationPlanWriter;
use Kalle\Pdf\Render\PdfTrailer;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfSerializationPlanWriterTest extends TestCase
{
    #[Test]
    public function it_writes_a_complete_pdf_serialization_plan_to_the_output(): void
    {
        $writer = new PdfSerializationPlanWriter();
        $output = new StringPdfOutput();

        $writer->write($this->serializationPlan(version: 1.4), $output);

        $contents = $output->contents();
        $catalogOffset = strpos($contents, "1 0 obj\n");
        $pagesOffset = strpos($contents, "2 0 obj\n");
        $infoOffset = strpos($contents, "3 0 obj\n");
        $xrefOffset = strpos($contents, "xref\n");

        self::assertStringStartsWith("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n", $contents);
        self::assertNotFalse($catalogOffset);
        self::assertNotFalse($pagesOffset);
        self::assertNotFalse($infoOffset);
        self::assertNotFalse($xrefOffset);
        self::assertStringContainsString(sprintf("%010d 00000 n \n", $catalogOffset), $contents);
        self::assertStringContainsString(sprintf("%010d 00000 n \n", $pagesOffset), $contents);
        self::assertStringContainsString(sprintf("%010d 00000 n \n", $infoOffset), $contents);
        self::assertStringContainsString("startxref\n{$xrefOffset}\n%%EOF", $contents);
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

            public function render(): string
            {
                return $this->id . " 0 obj\n" . $this->body . "\nendobj\n";
            }
        };
    }
}
