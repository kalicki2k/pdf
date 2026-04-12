<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function dirname;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
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

    public function testItRendersTextAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Annots [5 0 R]', $pdf);
        self::assertStringContainsString('/Subtype /Text', $pdf);
        self::assertStringContainsString('/Name /Comment', $pdf);
        self::assertStringContainsString('/Contents (Kommentar)', $pdf);
    }

    public function testItRendersHighlightAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->highlightAnnotation(40, 500, 80, 10, Color::rgb(1, 1, 0), 'Markiert', 'QA')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Annots [5 0 R]', $pdf);
        self::assertStringContainsString('/Subtype /Highlight', $pdf);
        self::assertStringContainsString('/QuadPoints [40 510 120 510 40 500 120 500]', $pdf);
        self::assertStringContainsString('/Contents (Markiert)', $pdf);
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
                link: LinkTarget::namedDestination('intro'),
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
                link: LinkTarget::externalUrl('https://example.com'),
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
                new TextSegment('Docs', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' und '),
                new TextSegment('API', LinkTarget::externalUrl('https://example.com/api')),
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

    public function testItRendersMergedTaggedPdfUaTextSegmentsWithTheSameLink(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->textSegments([
                new TextSegment('Read', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' docs', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' now'),
            ])
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertSame(1, substr_count($pdf, '/URI (https://example.com/docs)'));
        self::assertSame(1, substr_count($pdf, '/Type /StructElem /S /Link'));
        self::assertStringContainsString('/Contents (Read docs)', $pdf);
    }

    public function testItRendersWrappedTaggedPdfUaTextLinksAsOneLinkStructure(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->textSegments([
                new TextSegment('Read docs', LinkTarget::externalUrl('https://example.com/docs')),
            ], new TextOptions(width: 45))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertSame(2, substr_count($pdf, '/Subtype /Link'));
        self::assertSame(1, substr_count($pdf, '/Type /StructElem /S /Link'));
        self::assertSame(2, substr_count($pdf, '/Type /OBJR /Obj'));
        self::assertStringContainsString('/Alt (Read docs)', $pdf);
    }

    public function testItRendersSeparateAccessibleLabelsForPdfUaTextLinks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->textSegments([
                TextSegment::link(
                    'Docs',
                    TextLink::externalUrl(
                        'https://example.com/docs',
                        contents: 'Open docs section',
                        accessibleLabel: 'Read the documentation section',
                    ),
                ),
            ])
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Contents (Open docs section)', $pdf);
        self::assertStringContainsString('/Alt (Read the documentation section)', $pdf);
    }

    public function testItRendersTaggedPdfUaTablesWithCaptionHeaderAndCells(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
        )
            ->withPlacement(new TablePlacement(24.0, 270.0))
            ->withCaption(TableCaption::text('Quarterly summary'))
            ->withHeaderRows(
                TableRow::fromCells(
                    TableCell::text('Label', rowspan: 2)->withHeaderScope(TableHeaderScope::BOTH),
                    TableCell::text('Current', colspan: 2),
                ),
                TableRow::fromTexts('Planned', 'Actual'),
            )
            ->withRows(
                TableRow::fromCells(
                    TableCell::text('North', colspan: 2)->withHeaderScope(TableHeaderScope::ROW),
                    TableCell::text('12'),
                ),
                TableRow::fromTexts('South', '11', '10'),
            )
            ->withFooterRows(
                TableRow::fromTexts('Total', '23', '22'),
            );
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->table($table)
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/StructParents 0', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Table', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Caption', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TR', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TH', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TD', $pdf);
        self::assertStringContainsString('/A << /O /Table /Scope /Both /RowSpan 2 >>', $pdf);
        self::assertStringContainsString('/A << /O /Table /Scope /Column /ColSpan 2 >>', $pdf);
        self::assertStringContainsString('/A << /O /Table /Scope /Row /ColSpan 2 >>', $pdf);
        self::assertStringContainsString('/Caption << /MCID ', $pdf);
        self::assertStringContainsString('/TH << /MCID ', $pdf);
        self::assertStringContainsString('/TD << /MCID ', $pdf);
        self::assertStringContainsString('/Nums [0 [', $pdf);
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

    public function testItRendersAMinimalPdfA2uRegressionDocumentWithARealRepoFont(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Minimal Regression')
            ->author('kalle/pdf2')
            ->subject('Minimal PDF/A-2u regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->text('PDF/A-2u Regression Привет', new TextOptions(
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

        self::assertStringStartsWith("%PDF-1.7\n%\xE2\xE3\xCF\xD3\n", $pdf);
        self::assertStringContainsString('/OutputIntents [', $pdf);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $pdf);
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $pdf);
        self::assertStringContainsString('/Subtype /CIDFontType2', $pdf);
        self::assertStringContainsString('/Encoding /Identity-H', $pdf);
    }

    public function testItRendersPdfA2uLinkAnnotationsWithAppearanceStreams(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Link Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u link annotation regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->text('PDF/A-2u Link Regression Привет', new TextOptions(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->link('https://example.com/spec', 72, 670, 180, 16, 'Specification Link')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/A << /S /URI /URI (https://example.com/spec) >>', $pdf);
        self::assertStringContainsString('/AP << /N ', $pdf);
        self::assertStringContainsString('/Subtype /Form /FormType 1 /BBox [0 0 180 16]', $pdf);
        self::assertStringContainsString('/F 4', $pdf);
    }

    public function testItRendersPdfA2uTextAnnotationsWithAppearanceStreams(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Text Annotation Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u text annotation regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->text('PDF/A-2u Kommentar Regression Привет', new TextOptions(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->textAnnotation(72, 680, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Text', $pdf);
        self::assertStringContainsString('/AP << /N ', $pdf);
        self::assertStringContainsString('/Subtype /Form /FormType 1 /BBox [0 0 18 18]', $pdf);
        self::assertStringContainsString('/F 4', $pdf);
    }

    public function testItRendersPdfA2uHighlightAnnotationsWithAppearanceStreams(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Highlight Annotation Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u highlight annotation regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->text('PDF/A-2u Highlight Regression Привет', new TextOptions(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->highlightAnnotation(72, 680, 140, 12, Color::rgb(1, 1, 0), 'Markiert', 'QA')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Highlight', $pdf);
        self::assertStringContainsString('/QuadPoints [72 692 212 692 72 680 212 680]', $pdf);
        self::assertStringContainsString('/AP << /N ', $pdf);
        self::assertStringContainsString('/Subtype /Form /FormType 1 /BBox [0 0 140 12]', $pdf);
        self::assertStringContainsString('/F 4', $pdf);
    }

    public function testItRendersPdfA2uInternalLinksAndNamedDestinations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Internal Link Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u internal link regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->namedDestination('intro')
            ->text('Einleitung Привет', new TextOptions(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->newPage()
            ->text('Linkseite Привет', new TextOptions(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->linkToPage(1, 72, 680, 180, 16, 'Back To Page One')
            ->linkToPagePosition(1, 72, 760, 72, 650, 180, 16, 'Back To Heading')
            ->text('Zur Einleitung Привет', new TextOptions(
                x: 72,
                y: 620,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                link: LinkTarget::namedDestination('intro'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Dests << /intro [3 0 R /Fit] >>', $pdf);
        self::assertStringContainsString('/Dest [3 0 R /Fit]', $pdf);
        self::assertStringContainsString('/Dest [3 0 R /XYZ 72 760 null]', $pdf);
        self::assertStringContainsString('/Dest /intro', $pdf);
        self::assertGreaterThanOrEqual(3, substr_count($pdf, '/AP << /N '));
    }

    public function testItRendersPdfA2uRgbImagesWithoutTaggedRequirements(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Image Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u image regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->text('PDF/A-2u Bild Regression Привет', new TextOptions(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(72, 610, width: 160),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Image', $pdf);
        self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
        self::assertStringContainsString('/Filter /DCTDecode', $pdf);
        self::assertStringNotContainsString('/SMask ', $pdf);
    }
}
