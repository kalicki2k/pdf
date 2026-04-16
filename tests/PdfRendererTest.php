<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use Kalle\Pdf\Pdf;
use Kalle\Pdf\PdfRenderer;
use PHPUnit\Framework\TestCase;

final class PdfRendererTest extends TestCase
{
    public function testItRendersAMinimalPdfDocumentStructure(): void
    {
        $document = Pdf::document()
            ->withTitle('Minimal PDF Example')
            ->withAuthor('Kalle PDF')
            ->withPageName('cover')
            ->withPageLabel('Cover')
            ->writeText('Hello PDF', 72, 720)
            ->build();

        $pdf = PdfRenderer::make()->render($document);

        self::assertStringStartsWith('%PDF-1.7', $pdf);
        self::assertStringContainsString('/Type /Catalog', $pdf);
        self::assertStringContainsString('/Type /Pages', $pdf);
        self::assertStringContainsString('/Type /Page', $pdf);
        self::assertStringContainsString('/Contents ', $pdf);
        self::assertStringContainsString('/Type /Font', $pdf);
        self::assertStringContainsString('/BaseFont /Helvetica', $pdf);
        self::assertStringContainsString('stream', $pdf);
        self::assertStringContainsString('endstream', $pdf);
        self::assertStringContainsString('xref', $pdf);
        self::assertStringContainsString('trailer', $pdf);
        self::assertStringContainsString('startxref', $pdf);
        self::assertStringContainsString('%%EOF', $pdf);
        self::assertStringContainsString('Hello PDF', $pdf);
    }
}
