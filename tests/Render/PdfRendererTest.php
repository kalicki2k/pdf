<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Render\PdfRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfRendererTest extends TestCase
{
    #[Test]
    public function it_writes_cross_reference_entries_with_the_actual_object_offsets(): void
    {
        $document = new Document(
            profile: \Kalle\Pdf\Profile::standard(1.0),
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
    public function it_marks_missing_object_ids_as_free_entries_in_the_cross_reference_table(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
}
