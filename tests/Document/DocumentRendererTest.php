<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\StringOutput;
use PHPUnit\Framework\TestCase;

final class DocumentRendererTest extends TestCase
{
    public function testItRendersADocumentBuiltThroughThePublicApi(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->pageSize(PageSize::A5())
            ->text('Example Title', new TextOptions(
                x: 72,
                y: 720,
                fontSize: 18,
                fontName: 'Helvetica',
                color: Color::cmyk(0.1, 0.2, 0.3, 0.4),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString("1 0 obj\n", $pdf);
        self::assertStringContainsString("2 0 obj\n", $pdf);
        self::assertStringContainsString("3 0 obj\n", $pdf);
        self::assertStringContainsString("4 0 obj\n", $pdf);
        self::assertStringContainsString("5 0 obj\n", $pdf);
        self::assertStringContainsString('/Type /Page', $pdf);
        self::assertStringContainsString('/MediaBox [0 0 419.528 595.276] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R', $pdf);
        self::assertStringContainsString('<< /Length ', $pdf);
        self::assertStringContainsString("stream\nBT\n0.1 0.2 0.3 0.4 k\n/F1 18 Tf\n72 720 Td\n[<45>", $pdf);
        self::assertStringContainsString("] TJ\nET\nendstream", $pdf);
        self::assertStringContainsString('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', $pdf);
        self::assertStringContainsString("xref\n", $pdf);
        self::assertStringContainsString("trailer\n", $pdf);
        self::assertStringContainsString('/Root 1 0 R', $pdf);
        self::assertStringContainsString('/Info 6 0 R', $pdf);
        self::assertStringContainsString('/Title (Example Title)', $pdf);
        self::assertStringContainsString('/Author (Sebastian Kalicki)', $pdf);
        self::assertStringEndsWith('%%EOF', $pdf);
    }

    public function testItRendersPdf10WesternStandardEncodingWithDifferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::standard(Version::V1_0))
            ->text('ÄÖÜäöüß')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringStartsWith('%PDF-1.0', $pdf);
        self::assertStringContainsString('/BaseEncoding /StandardEncoding /Differences [128 /Adieresis', $pdf);
        self::assertStringContainsString('288085868a9a9fa72920546a', bin2hex($pdf));
    }

    public function testItRendersIsoLatin1EncodingWhenExplicitlyConfigured(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdf10())
            ->text('ÄÖÜäöüß', new TextOptions(
                fontName: StandardFont::HELVETICA->value,
                fontEncoding: StandardFontEncoding::ISO_LATIN_1,
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringStartsWith('%PDF-1.0', $pdf);
        self::assertStringContainsString('/Encoding /ISOLatin1Encoding', $pdf);
        self::assertStringContainsString('28c4d6dce4f6fcdf2920546a', bin2hex($pdf));
    }
}
