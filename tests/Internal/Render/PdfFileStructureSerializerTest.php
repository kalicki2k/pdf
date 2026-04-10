<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Render;

use Kalle\Pdf\Internal\Render\PdfFileStructure;
use Kalle\Pdf\Internal\Render\PdfFileStructureSerializer;
use Kalle\Pdf\Internal\Render\PdfObjectOffsets;
use Kalle\Pdf\Internal\Render\PdfTrailer;
use Kalle\Pdf\Internal\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfFileStructureSerializerTest extends TestCase
{
    #[Test]
    public function it_writes_header_cross_reference_trailer_and_footer(): void
    {
        $serializer = new PdfFileStructureSerializer();
        $output = new StringPdfOutput();
        $fileStructure = new PdfFileStructure(1.4, new PdfTrailer(1, 2, null, ['abc', 'def']));

        $serializer->writeHeader($fileStructure, $output);
        $offsets = new PdfObjectOffsets([1 => 15, 3 => 42]);

        $serializer->writeCrossReferenceTable($offsets, $output);
        $serializer->writeTrailer($output, $offsets, $fileStructure->trailer);
        $serializer->writeFooter($output, 99);

        $contents = $output->contents();

        self::assertStringStartsWith("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n", $contents);
        self::assertStringContainsString("xref\n0 4\n0000000000 65535 f \n0000000015 00000 n \n0000000000 65535 f \n0000000042 00000 n \n", $contents);
        self::assertStringContainsString("trailer\n<< /Size 4\n/Root 1 0 R\n/Info 2 0 R\n/ID [<abc> <def>] >>\n", $contents);
        self::assertStringEndsWith("startxref\n99\n%%EOF", $contents);
    }

    #[Test]
    public function it_writes_the_cross_reference_section_using_the_current_output_offset(): void
    {
        $serializer = new PdfFileStructureSerializer();
        $output = new StringPdfOutput();
        $fileStructure = new PdfFileStructure(1.4, new PdfTrailer(1, 2, null, ['abc', 'def']));

        $serializer->writeHeader($fileStructure, $output);
        $offsets = new PdfObjectOffsets([1 => 15, 3 => 42]);
        $expectedStartXref = $output->offset();

        $serializer->writeCrossReferenceSection($output, $offsets, $fileStructure);

        $contents = $output->contents();

        self::assertStringContainsString("xref\n0 4\n", $contents);
        self::assertStringContainsString("trailer\n<< /Size 4\n/Root 1 0 R\n/Info 2 0 R\n/ID [<abc> <def>] >>\n", $contents);
        self::assertStringEndsWith("startxref\n{$expectedStartXref}\n%%EOF", $contents);
    }
}
