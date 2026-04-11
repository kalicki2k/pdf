<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Writer\StringOutput;
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

    public function testItSavesADocumentToAPath(): void
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

        $savedPath = Pdf::save($document, $path);

        self::assertSame($path, $savedPath);
        self::assertFileExists($path);

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('%%EOF', $contents);

        unlink($path);
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
}
