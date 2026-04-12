<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use Kalle\Pdf\Writer\StringOutput;
use PHPUnit\Framework\TestCase;

use function dirname;

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
        self::assertStringContainsString("6 0 obj\n", $pdf);
        self::assertStringContainsString("7 0 obj\n", $pdf);
        self::assertStringContainsString('/Type /Page', $pdf);
        self::assertStringContainsString('/Type /Catalog /Pages 2 0 R /Metadata 6 0 R', $pdf);
        self::assertStringContainsString('/MediaBox [0 0 419.528 595.276] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R', $pdf);
        self::assertStringContainsString('<< /Length ', $pdf);
        self::assertStringContainsString("stream\nBT\n0.1 0.2 0.3 0.4 k\n/F1 18 Tf\n72 720 Td\n[<45>", $pdf);
        self::assertStringContainsString("] TJ\nET\nendstream", $pdf);
        self::assertStringContainsString('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', $pdf);
        self::assertStringContainsString('<< /Type /Metadata /Subtype /XML /Length ', $pdf);
        self::assertStringContainsString("xref\n", $pdf);
        self::assertStringContainsString("trailer\n", $pdf);
        self::assertStringContainsString('/Root 1 0 R', $pdf);
        self::assertStringContainsString('/Info 7 0 R', $pdf);
        self::assertStringContainsString('/Title (Example Title)', $pdf);
        self::assertStringContainsString('/Author (Sebastian Kalicki)', $pdf);
        self::assertStringContainsString('/CreationDate (', $pdf);
        self::assertStringContainsString('/ModDate (', $pdf);
        self::assertMatchesRegularExpression('/<xmp:CreateDate>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}<\/xmp:CreateDate>/', $pdf);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Example Title</rdf:li>', $pdf);
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

    public function testItRendersTaggedFigureStructureForPdfUaImages(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(40, 500, width: 120),
                ImageAccessibility::alternativeText('Logo'),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/StructTreeRoot', $pdf);
        self::assertStringContainsString('/StructParents 0', $pdf);
        self::assertStringContainsString('/Figure << /MCID 0 >> BDC', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Figure', $pdf);
        self::assertStringContainsString('/Alt (Logo)', $pdf);
        self::assertStringContainsString('/Nums [0 [', $pdf);
    }

    public function testItRendersLinkAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->link('https://example.com', 40, 500, 120, 16, 'Open Example')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Annots [5 0 R]', $pdf);
        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/A << /S /URI /URI (https://example.com) >>', $pdf);
        self::assertStringContainsString('/Contents (Open Example)', $pdf);
    }

    public function testItRendersInternalLinkAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Page 1')
            ->newPage()
            ->linkToPage(1, 40, 500, 120, 16, 'Back to page 1')
            ->linkToPagePosition(1, 72, 700, 40, 460, 120, 16, 'Back to heading')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Dest [3 0 R /Fit]', $pdf);
        self::assertStringContainsString('/Dest [3 0 R /XYZ 72 700 null]', $pdf);
    }

    public function testItRendersNamedDestinationLinks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->namedDestination('intro')
            ->text('Open intro', new TextOptions(
                link: \Kalle\Pdf\Page\LinkTarget::namedDestination('intro'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Dests << /intro [3 0 R /Fit] >>', $pdf);
        self::assertStringContainsString('/Dest /intro', $pdf);
    }

    public function testItRendersTaggedPdfUaLinkAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->link('https://example.com', 40, 500, 120, 16, 'Open Example')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Tabs /S', $pdf);
        self::assertStringContainsString('/StructParent 0', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Link', $pdf);
        self::assertStringContainsString('/Type /OBJR /Obj', $pdf);
    }

    public function testItRendersTaggedPdfUaTextLinks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->text('Read more', new TextOptions(
                link: \Kalle\Pdf\Page\LinkTarget::externalUrl('https://example.com'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Link << /MCID 0 >> BDC', $pdf);
        self::assertStringContainsString('/StructParent 0', $pdf);
        self::assertStringContainsString('/K [0 << /Type /OBJR /Obj', $pdf);
    }

    public function testItRendersMultipleTextSegmentLinks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textSegments([
                new TextSegment('Docs', \Kalle\Pdf\Page\LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' und '),
                new TextSegment('API', \Kalle\Pdf\Page\LinkTarget::externalUrl('https://example.com/api')),
            ])
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/URI (https://example.com/docs)', $pdf);
        self::assertStringContainsString('/Contents (Docs)', $pdf);
        self::assertStringContainsString('/URI (https://example.com/api)', $pdf);
        self::assertStringContainsString('/Contents (API)', $pdf);
    }

    public function testItRendersAMinimalPdfA1bRegressionDocumentWithARealRepoFont(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('PDF/A-1b Minimal Regression')
            ->author('kalle/pdf2')
            ->subject('Minimal PDF/A-1b regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->text('PDF/A-1b Regression Привет', new TextOptions(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringStartsWith("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n", $pdf);
        self::assertStringContainsString('/OutputIntents [', $pdf);
        self::assertStringContainsString('/DestOutputProfile', $pdf);
        self::assertStringContainsString('/S /GTS_PDFA1', $pdf);
        self::assertStringContainsString('<pdfaid:part>1</pdfaid:part>', $pdf);
        self::assertStringContainsString('<pdfaid:conformance>B</pdfaid:conformance>', $pdf);
        self::assertStringContainsString('/Subtype /CIDFontType2', $pdf);
        self::assertStringContainsString('NotoSans-Regular', $pdf);
        self::assertStringContainsString('/Encoding /Identity-H', $pdf);
    }
}
