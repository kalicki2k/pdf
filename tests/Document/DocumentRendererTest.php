<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function dirname;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuildException;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\FormFieldRenderContext;
use Kalle\Pdf\Document\Form\WidgetFormField;
use Kalle\Pdf\Document\ListOptions;
use Kalle\Pdf\Document\ListType;
use Kalle\Pdf\Document\Outline;
use Kalle\Pdf\Document\OutlineStyle;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Drawing\GraphicsAccessibility;
use Kalle\Pdf\Drawing\StrokeStyle;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\AnnotationBorderStyle;
use Kalle\Pdf\Page\AnnotationMetadata;
use Kalle\Pdf\Page\FileAttachmentAnnotationOptions;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\LineAnnotationOptions;
use Kalle\Pdf\Page\LineEndingStyle;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\MarkupAnnotationOptions;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\PolygonAnnotationOptions;
use Kalle\Pdf\Page\ShapeAnnotationOptions;
use Kalle\Pdf\Page\TextAnnotationOptions;
use Kalle\Pdf\Tests\Image\BmpFixture;
use Kalle\Pdf\Tests\Image\GifFixture;
use Kalle\Pdf\Tests\Image\JpegFixture;
use Kalle\Pdf\Tests\Image\TiffFixture;
use Kalle\Pdf\Tests\Image\WebpFixture;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use Kalle\Pdf\Writer\Output;
use Kalle\Pdf\Writer\StringOutput;
use PHPUnit\Framework\TestCase;

final class DocumentRendererTest extends TestCase
{
    public function testItRendersADocumentBuiltThroughThePublicApi(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->keywords('archive, invoice')
            ->pageSize(PageSize::A5())
            ->text('Example Title', TextOptions::make(
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
        self::assertStringContainsString('/Keywords (archive, invoice)', $pdf);
        self::assertStringContainsString('/CreationDate (', $pdf);
        self::assertStringContainsString('/ModDate (', $pdf);
        self::assertMatchesRegularExpression('/<xmp:CreateDate>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}<\/xmp:CreateDate>/', $pdf);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Example Title</rdf:li>', $pdf);
        self::assertStringContainsString('<pdf:Keywords>archive, invoice</pdf:Keywords>', $pdf);
        self::assertStringEndsWith('%%EOF', $pdf);
    }

    public function testItWrapsPdfAValidationFailuresInABuildException(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3b())
            ->title('Invoice')
            ->language('de-DE')
            ->text('Hello world')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $this->expectException(DocumentBuildException::class);
        $this->expectExceptionMessage('Document build failed for profile PDF/A-3b.');
        $this->expectExceptionMessage('requires embedded fonts');
        $this->expectExceptionMessage('Use embedded fonts via TextOptions(embeddedFont: ...)');

        $renderer->write($document, $output);
    }

    public function testItRendersBasePdfA4DocumentsWithinTheCurrentSupportedScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4())
            ->title('Archive Copy')
            ->text(
                'Archive Copy',
                TextOptions::make(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/inter/static/Inter-Regular.ttf'),
                ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringStartsWith('%PDF-2.0', $pdf);
        self::assertStringContainsString('<pdfaid:part>4</pdfaid:part>', $pdf);
        self::assertStringContainsString('<pdfaid:rev>2020</pdfaid:rev>', $pdf);
        self::assertStringNotContainsString('/OutputIntents', $pdf);
        self::assertStringNotContainsString("\n/Info ", $pdf);
    }

    public function testItRendersConstrainedPdfA4eDocumentsWithinTheCurrentSupportedScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4e())
            ->title('Engineering Archive Copy')
            ->text(
                'Engineering Archive Copy',
                TextOptions::make(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/inter/static/Inter-Regular.ttf'),
                ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringStartsWith('%PDF-2.0', $pdf);
        self::assertStringContainsString('<pdfaid:part>4</pdfaid:part>', $pdf);
        self::assertStringContainsString('<pdfaid:rev>2020</pdfaid:rev>', $pdf);
        self::assertStringContainsString('<pdfaid:conformance>E</pdfaid:conformance>', $pdf);
        self::assertStringNotContainsString('/OutputIntents', $pdf);
        self::assertStringNotContainsString("\n/Info ", $pdf);
    }

    public function testItRendersPdfA4fDocumentsWithDocumentLevelAssociatedFiles(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4f())
            ->title('Archive Package')
            ->text(
                'Archive Package',
                TextOptions::make(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/inter/static/Inter-Regular.ttf'),
                ),
            )
            ->attachment('data.xml', '<root/>', 'Source data', 'application/xml')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringStartsWith('%PDF-2.0', $pdf);
        self::assertStringContainsString('<pdfaid:part>4</pdfaid:part>', $pdf);
        self::assertStringContainsString('<pdfaid:rev>2020</pdfaid:rev>', $pdf);
        self::assertStringContainsString('<pdfaid:conformance>F</pdfaid:conformance>', $pdf);
        self::assertStringContainsString('/AFRelationship /Data', $pdf);
        self::assertStringContainsString('/AF [', $pdf);
        self::assertStringNotContainsString('/OutputIntents', $pdf);
        self::assertStringNotContainsString("\n/Info ", $pdf);
    }

    public function testItRendersPdfA4LinkAnnotationsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4())
            ->title('Archive Copy')
            ->text(
                'Archive Copy',
                TextOptions::make(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/inter/static/Inter-Regular.ttf'),
                ),
            )
            ->link('https://example.com/spec', 72, 670, 180, 16, 'Specification Link')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/A << /S /URI /URI (https://example.com/spec) >>', $pdf);
        self::assertStringContainsString('/AP << /N ', $pdf);
        self::assertStringNotContainsString('/OutputIntents', $pdf);
        self::assertStringNotContainsString("\n/Info ", $pdf);
    }

    public function testItRendersPdfA4fAcroFormChoiceFieldsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4f())
            ->title('Archive Copy')
            ->comboBox('status', 72, 680, 140, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->listBox('skills', 72, 620, 140, 44, ['php' => 'PHP', 'pdf' => 'PDF'], ['php'], 'Skills')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/AcroForm ', $pdf);
        self::assertStringContainsString('/FT /Ch', $pdf);
        self::assertStringNotContainsString('/Helv', $pdf);
    }

    public function testItRendersEmbeddedFontTextWithNewlines(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text(
                "Kundenfirma Mueller GmbH\nz. Hd. Anna Mueller\nBeispielweg 8",
                TextOptions::make(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/inter/static/Inter-Regular.ttf'),
                    width: 220,
                    fontSize: 10,
                    lineHeight: 14,
                ),
            )
            ->build();

        $output = new StringOutput();
        (new DocumentRenderer())->write($document, $output);

        self::assertStringContainsString('/Subtype /TrueType', $output->contents());
    }

    public function testItDoesNotWrapWriterPhaseInvalidArgumentExceptionsAsBuildExceptions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->title('Example Title')
            ->text('Hello world')
            ->build();
        $renderer = new DocumentRenderer();
        $output = new class () implements Output {
            public function write(string $bytes): void
            {
                throw new InvalidArgumentException('Output rejected bytes.');
            }

            public function offset(): int
            {
                return 0;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Output rejected bytes.');

        $renderer->write($document, $output);
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
            ->text('ÄÖÜäöüß', TextOptions::make(
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

    public function testItRendersExplicitTextLinesWithoutBlankLineSpacing(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textLines(['Firma', 'Strasse 1'], TextOptions::make(
                x: 72.0,
                y: 720.0,
                fontSize: 10.0,
                lineHeight: 12.0,
            ))
            ->text('Ort', TextOptions::make(
                x: 72.0,
                fontSize: 10.0,
                lineHeight: 12.0,
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('72 720 Td', $pdf);
        self::assertStringContainsString('72 696 Td', $pdf);
        self::assertStringNotContainsString('72 684 Td', $pdf);
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

    public function testItRendersTaggedFigureStructureForSemanticGraphics(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->line(
                40,
                500,
                160,
                500,
                new StrokeStyle(2.0, Color::rgb(0, 0, 1)),
                GraphicsAccessibility::alternativeText('Blue divider'),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Figure << /MCID 0 >> BDC', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Figure', $pdf);
        self::assertStringContainsString('/Alt (Blue divider)', $pdf);
    }

    public function testItRendersTaggedPdfA1aTextStructure(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('Einleitung Привет', TextOptions::make(
                tag: TaggedStructureTag::H1,
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->text('Erster Absatz mit strukturiertem Inhalt. Привет.', TextOptions::make(
                x: 72,
                y: 720,
                width: 320,
                fontSize: 12,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/StructTreeRoot', $pdf);
        self::assertStringContainsString('/MarkInfo << /Marked true >>', $pdf);
        self::assertStringContainsString('/H1 << /MCID 0 >> BDC', $pdf);
        self::assertStringContainsString('/P << /MCID 1 >> BDC', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /H1', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /P', $pdf);
    }

    public function testItRejectsTaggedPdfA1aWithoutDocumentLanguage(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->text('Absatztext Привет', TextOptions::make(
                x: 72,
                y: 720,
                width: 320,
                fontSize: 12,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $this->expectException(DocumentBuildException::class);
        $this->expectExceptionMessage('Document build failed for profile PDF/A-1a.');
        $this->expectExceptionMessage('Profile PDF/A-1a requires a document language.');

        $renderer->write($document, $output);
    }

    public function testItRendersTaggedPdfA1aBulletListStructure(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->list(
                ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
                text: TextOptions::make(
                    x: 72,
                    y: 760,
                    width: 320,
                    fontSize: 12,
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/StructTreeRoot', $pdf);
        self::assertStringContainsString('/Lbl << /MCID 0 >> BDC', $pdf);
        self::assertStringContainsString('/LBody << /MCID 1 >> BDC', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /L', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /LI', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Lbl', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /LBody', $pdf);
    }

    public function testItRendersTaggedPdfA1aNumberedListStructure(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->list(
                ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
                new ListOptions(type: ListType::NUMBERED, start: 3, marker: '%d)'),
                TextOptions::make(
                    x: 72,
                    y: 760,
                    width: 320,
                    fontSize: 12,
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/StructTreeRoot', $pdf);
        self::assertStringContainsString('/Lbl << /MCID 0 >> BDC', $pdf);
        self::assertStringContainsString('/LBody << /MCID 1 >> BDC', $pdf);
        self::assertStringContainsString('<00010002> Tj', $pdf);
        self::assertStringContainsString('<00130002> Tj', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /L', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /LI', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Lbl', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /LBody', $pdf);
    }

    public function testItRendersTaggedPdfA1aTableStructure(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->table(
                Table::define(
                    TableColumn::fixed(120.0),
                    TableColumn::fixed(120.0),
                    TableColumn::fixed(120.0),
                )
                    ->withOptions(
                        (TableOptions::make())
                            ->withPlacement(TablePlacement::at(72.0, 700.0, 360.0))
                            ->withCaption(TableCaption::text('Quarterly summary Привет'))
                            ->withTextOptions(TextOptions::make(
                                fontSize: 12,
                                lineHeight: 15,
                                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                            )),
                    )
                    ->withHeaderRows(
                        TableRow::fromCells(
                            TableCell::text('Регион', rowspan: 2)->withHeaderScope(TableHeaderScope::BOTH),
                            TableCell::text('План', colspan: 2),
                        ),
                        TableRow::fromTexts('Q1', 'Q2 Привет'),
                    )
                    ->withRows(
                        TableRow::fromCells(
                            TableCell::text('Север')->withHeaderScope(TableHeaderScope::ROW),
                            TableCell::text('12'),
                            TableCell::text('14'),
                        ),
                        TableRow::fromTexts('Юг', '10', '11'),
                    )
                    ->withFooterRows(
                        TableRow::fromTexts('Итого', '22', '25'),
                    ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Type /StructElem /S /Table', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Sect', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TH', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TD', $pdf);
    }

    public function testItRendersMixedTaggedPdfA1aStructure(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('Projektübersicht Привет', TextOptions::make(
                tag: TaggedStructureTag::H1,
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->text('Absatztext Привет', TextOptions::make(
                x: 72,
                y: 724,
                width: 320,
                fontSize: 12,
                lineHeight: 16,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->list(
                ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
                text: TextOptions::make(
                    x: 72,
                    y: 676,
                    width: 220,
                    fontSize: 12,
                    lineHeight: 16,
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                ),
            )
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(320, 610, width: 140),
                ImageAccessibility::alternativeText('Projektgrafik'),
            )
            ->linkToPage(1, 72, 560, 180, 16, 'Spezifikation öffnen')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Type /StructElem /S /H1', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /P', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /L', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Figure', $pdf);
        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/AP << /N ', $pdf);
    }

    public function testItRendersTaggedPdfA1aLayoutGraphicsAsArtifacts(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->newPage(new PageOptions(
                backgroundColor: Color::rgb(0.95, 0.95, 0.95),
            ))
            ->table(
                Table::define(
                    TableColumn::fixed(120.0),
                )
                    ->withOptions(
                        (TableOptions::make())
                            ->withPlacement(TablePlacement::at(72.0, 700.0, 120.0))
                            ->withTextOptions(TextOptions::make(
                                fontSize: 12,
                                lineHeight: 15,
                                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                            )),
                    )
                    ->withRows(
                        TableRow::fromCells(
                            TableCell::text('Cell')->withBackgroundColor(Color::rgb(0.9, 0.9, 0.9)),
                        ),
                    ),
            )
            ->text('Text content Привет', TextOptions::make(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(240, 620, width: 80),
                ImageAccessibility::alternativeText('Meaningful image'),
            )
            ->image(
                ImageSource::jpeg('jpeg-bytes', 40, 40, ImageColorSpace::RGB),
                ImagePlacement::at(340, 620, width: 32),
                ImageAccessibility::decorative(),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString("/Artifact BMC\nq\n0.95 0.95 0.95 rg", $pdf);
        self::assertStringContainsString("/Artifact BMC\nq\n0.9 0.9 0.9 rg", $pdf);
        self::assertStringContainsString("/Artifact BMC\nq\n0.5 w", $pdf);
        self::assertStringContainsString('/Figure << /MCID ', $pdf);
        self::assertStringContainsString("/Artifact BMC\nq\n32 0 0 32 340 620 cm", $pdf);
        self::assertStringContainsString('/P << /MCID ', $pdf);
    }

    public function testItKeepsMultipleTaggedLayoutArtifactsOutOfTheLogicalStructure(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->newPage(new PageOptions(
                backgroundColor: Color::rgb(0.95, 0.95, 0.95),
            ))
            ->table(
                Table::define(
                    TableColumn::fixed(120.0),
                    TableColumn::fixed(120.0),
                )
                    ->withOptions(
                        (TableOptions::make())
                            ->withPlacement(TablePlacement::at(72.0, 700.0, 240.0))
                            ->withTextOptions(TextOptions::make(
                                fontSize: 12,
                                lineHeight: 15,
                                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                            )),
                    )
                    ->withRows(
                        TableRow::fromCells(
                            TableCell::text('Left')->withBackgroundColor(Color::rgb(0.9, 0.9, 0.9)),
                            TableCell::text('Right')->withBackgroundColor(Color::rgb(0.85, 0.85, 0.85)),
                        ),
                    ),
            )
            ->text('Text content Привет', TextOptions::make(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertGreaterThanOrEqual(4, substr_count($pdf, '/Artifact BMC'));
        self::assertStringNotContainsString('/Artifact << /MCID ', $pdf);
        self::assertSame(0, substr_count($pdf, '/Type /StructElem /S /Artifact'));
        self::assertStringContainsString('/TD << /MCID ', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Table', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /P', $pdf);
    }

    public function testItRendersTheSupportedPdfA1aStructureSurface(): void
    {
        $table = Table::define(
            TableColumn::fixed(120.0),
            TableColumn::fixed(120.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withPlacement(TablePlacement::at(72.0, 520.0, 240.0))
                    ->withCaption(TableCaption::text('Kurzuebersicht Привет'))
                    ->withTextOptions(TextOptions::make(
                        fontSize: 12,
                        lineHeight: 15,
                        embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    )),
            )
            ->withHeaderRows(
                TableRow::fromTexts('Spalte A', 'Spalte B'),
            )
            ->withRows(
                TableRow::fromTexts('Wert 1', 'Wert 2'),
            );

        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('Ueberschrift Привет', TextOptions::make(
                tag: TaggedStructureTag::H1,
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->text('Absatztext Привет', TextOptions::make(
                x: 72,
                y: 724,
                width: 320,
                fontSize: 12,
                lineHeight: 16,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->list(
                ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
                text: TextOptions::make(
                    x: 72,
                    y: 676,
                    width: 220,
                    fontSize: 12,
                    lineHeight: 16,
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                ),
            )
            ->table($table)
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(320, 610, width: 140),
                ImageAccessibility::alternativeText('Projektgrafik'),
            )
            ->linkToPage(1, 72, 480, 180, 16, 'Spezifikation oeffnen')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Type /StructElem /S /H1', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /P', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /L', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /LI', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Lbl', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /LBody', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Table', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Caption', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TR', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TH', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /TD', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Figure', $pdf);
        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/AP << /N ', $pdf);
    }

    public function testItRendersTaggedPdfA1aMultipageStructure(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('Kapitel Eins Привет', TextOptions::make(
                tag: TaggedStructureTag::H1,
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->text('Erste Seite Привет', TextOptions::make(
                x: 72,
                y: 724,
                width: 320,
                fontSize: 12,
                lineHeight: 16,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->newPage()
            ->text('Kapitel Zwei Привет', TextOptions::make(
                tag: TaggedStructureTag::H1,
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->text('Zweite Seite Привет', TextOptions::make(
                x: 72,
                y: 724,
                width: 320,
                fontSize: 12,
                lineHeight: 16,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/StructTreeRoot', $pdf);
        self::assertStringContainsString('/StructParents 0', $pdf);
        self::assertStringContainsString('/StructParents 1', $pdf);
        self::assertStringContainsString('/Nums [0 [', $pdf);
        self::assertStringContainsString('1 [', $pdf);
        self::assertGreaterThanOrEqual(2, substr_count($pdf, '/Type /StructElem /S /H1'));
        self::assertGreaterThanOrEqual(2, substr_count($pdf, '/Type /StructElem /S /P'));
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

    public function testItRendersEmbeddedFileAttachments(): void
    {
        $document = new Document(
            attachments: [
                new FileAttachment(
                    'demo.txt',
                    new EmbeddedFile('hello', 'text/plain'),
                    'Demo attachment',
                ),
            ],
        );

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Names << /EmbeddedFiles << /Names [(demo.txt) 6 0 R] >> >>', $pdf);
        self::assertStringContainsString('<< /Type /EmbeddedFile /Length 5 /Params << /Size 5 >> /Subtype /text#2Fplain >>', $pdf);
        self::assertStringContainsString('/Type /Filespec /F (demo.txt) /UF (demo.txt)', $pdf);
        self::assertStringContainsString('/Desc (Demo attachment)', $pdf);
    }

    public function testItRendersDocumentLevelAssociatedFilesForPdf20(): void
    {
        $document = new Document(
            profile: Profile::pdf20(),
            attachments: [
                new FileAttachment(
                    'data.xml',
                    new EmbeddedFile('<root/>', 'application/xml'),
                    'Machine-readable source',
                    AssociatedFileRelationship::DATA,
                ),
            ],
        );

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/AF [6 0 R]', $pdf);
        self::assertStringContainsString('/AFRelationship /Data', $pdf);
        self::assertStringContainsString('/Names << /EmbeddedFiles << /Names [(data.xml) 6 0 R] >> >>', $pdf);
    }

    public function testItRendersAnAcroFormAndWidgetField(): void
    {
        $document = new Document(
            acroForm: new AcroForm()->withField($this->testWidgetField()),
        );

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/AcroForm 5 0 R', $pdf);
        self::assertStringContainsString('/Fields [6 0 R]', $pdf);
        self::assertStringContainsString('/Subtype /Widget', $pdf);
        self::assertStringContainsString('/FT /Tx', $pdf);
        self::assertStringContainsString('/Annots [6 0 R]', $pdf);
    }

    public function testItRendersTaggedPdfUaSingleWidgetFormFields(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Form')
            ->language('de-DE')
            ->textField('customer_name', 40, 500, 160, 18, 'Ada', 'Customer name')
            ->checkbox('accept_terms', 40, 460, 14, true, 'Accept terms')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Tabs /S', $pdf);
        self::assertSame(2, substr_count($pdf, '/Type /StructElem /S /Form'));
        self::assertStringContainsString('/StructParent 0', $pdf);
        self::assertStringContainsString('/StructParent 1', $pdf);
        self::assertStringContainsString('/Alt (Customer name)', $pdf);
        self::assertStringContainsString('/Alt (Accept terms)', $pdf);
        self::assertSame(2, substr_count($pdf, '/Type /OBJR /Obj'));
    }

    public function testItRendersTaggedPdfA1aSingleWidgetFormFields(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Form')
            ->language('de-DE')
            ->textField('customer_name', 40, 500, 160, 18, 'Ada', 'Customer name')
            ->checkbox('accept_terms', 40, 460, 14, true, 'Accept terms')
            ->signatureField('approval_signature', 40, 420, 140, 28, 'Approval signature')
            ->build();

        $this->expectException(DocumentBuildException::class);
        $this->expectExceptionMessage('Document build failed for profile PDF/A-1a.');
        $this->expectExceptionMessage('Profile PDF/A-1a only allows text and choice fields in the PDF/A-1a form policy.');

        new DocumentRenderer()->write($document, new StringOutput());
    }

    public function testItRendersTaggedPdfA1aRadioButtonGroups(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Form')
            ->language('de-DE')
            ->radioButton('delivery', 'standard', 40, 500, 14, false, 'Standard delivery', 'Delivery method')
            ->radioButton('delivery', 'express', 80, 500, 14, true, 'Express delivery')
            ->build();

        $this->expectException(DocumentBuildException::class);
        $this->expectExceptionMessage('Document build failed for profile PDF/A-1a.');
        $this->expectExceptionMessage('Profile PDF/A-1a only allows text and choice fields in the PDF/A-1a form policy.');

        new DocumentRenderer()->write($document, new StringOutput());
    }

    public function testItRendersATextField(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textField('customer_name', 40, 500, 160, 18, 'Ada', 'Customer name')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/AcroForm 5 0 R', $pdf);
        self::assertStringContainsString('/FT /Tx', $pdf);
        self::assertStringContainsString('/T (customer_name)', $pdf);
        self::assertStringContainsString('/TU (Customer name)', $pdf);
        self::assertStringContainsString('/DA (/Helv 12 Tf 0 g)', $pdf);
        self::assertStringContainsString('/V (Ada)', $pdf);
    }

    public function testItRendersACheckboxWithAppearanceStreams(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->checkbox('accept_terms', 40, 500, 14, true, 'Accept terms')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/FT /Btn', $pdf);
        self::assertStringContainsString('/T (accept_terms)', $pdf);
        self::assertStringContainsString('/TU (Accept terms)', $pdf);
        self::assertStringContainsString('/V /Yes', $pdf);
        self::assertStringContainsString('/AS /Yes', $pdf);
        self::assertStringContainsString('/Off 7 0 R /Yes 8 0 R', $pdf);
        self::assertStringContainsString('/Subtype /Form', $pdf);
    }

    public function testItRendersARadioButtonGroup(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->radioButton('delivery', 'standard', 40, 500, 14, false, 'Standard delivery', 'Delivery method')
            ->radioButton('delivery', 'express', 80, 500, 14, true, 'Express delivery')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Kids [7 0 R 10 0 R]', $pdf);
        self::assertStringContainsString('/V /express', $pdf);
        self::assertStringContainsString('/Parent 6 0 R', $pdf);
        self::assertStringContainsString('/Annots [7 0 R 10 0 R]', $pdf);
    }

    public function testItRendersAComboBox(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->comboBox('status', 40, 500, 120, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/FT /Ch', $pdf);
        self::assertStringContainsString('/Ff 131072', $pdf);
        self::assertStringContainsString('/Opt [[(new) (New)] [(done) (Done)]]', $pdf);
    }

    public function testItRendersAListBox(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->listBox('skills', 40, 500, 120, 48, ['php' => 'PHP', 'pdf' => 'PDF'], ['php', 'pdf'], 'Skills')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/FT /Ch', $pdf);
        self::assertStringContainsString('/Ff 2097152', $pdf);
        self::assertStringContainsString('/V [(php) (pdf)]', $pdf);
    }

    public function testItRendersAPushButton(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pushButton('open_docs', 'Open docs', 40, 500, 120, 18, 'Open documentation', 'https://example.com/docs')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/FT /Btn', $pdf);
        self::assertStringContainsString('/Ff 65536', $pdf);
        self::assertStringContainsString('/MK << /CA (Open docs) >>', $pdf);
        self::assertStringContainsString('/A << /S /URI /URI (https://example.com/docs) >>', $pdf);
    }

    public function testItRendersTaggedPdfA1aChoiceFields(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Form')
            ->language('de-DE')
            ->comboBox('status', 40, 500, 120, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->listBox('skills', 40, 450, 120, 48, ['php' => 'PHP', 'pdf' => 'PDF'], ['php', 'pdf'], 'Skills')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertSame(2, substr_count($pdf, '/Type /StructElem /S /Form'));
        self::assertStringContainsString('/Alt (Status)', $pdf);
        self::assertStringContainsString('/Alt (Skills)', $pdf);
        self::assertStringContainsString('/AP << /N ', $pdf);
        self::assertStringContainsString('/DR << /Font << /F0 ', $pdf);
        self::assertStringContainsString('/F0 12 Tf', $pdf);
        self::assertStringContainsString(' Tj', $pdf);
        self::assertStringNotContainsString('/Helv', $pdf);
    }

    public function testItRejectsPdfA1aPushButtons(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Form')
            ->language('de-DE')
            ->pushButton('ack', 'Acknowledge', 40, 380, 120, 18, 'Acknowledge')
            ->build();

        $this->expectException(DocumentBuildException::class);
        $this->expectExceptionMessage('Document build failed for profile PDF/A-1a.');
        $this->expectExceptionMessage(
            'Profile PDF/A-1a only allows text and choice fields in the PDF/A-1a form policy.',
        );

        new DocumentRenderer()->write($document, new StringOutput());
    }

    public function testItRendersASignatureField(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->signatureField('approval_signature', 40, 500, 140, 28, 'Approval signature')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/SigFlags 1', $pdf);
        self::assertStringContainsString('/FT /Sig', $pdf);
        self::assertStringContainsString('/T (approval_signature)', $pdf);
        self::assertStringContainsString('/TU (Approval signature)', $pdf);
        self::assertStringContainsString('/AP << /N 7 0 R >>', $pdf);
        self::assertStringContainsString('/Subtype /Form', $pdf);
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

    private function testWidgetField(int $pageNumber = 1): WidgetFormField
    {
        return new readonly class ('customer_name', $pageNumber, 10.0, 20.0, 80.0, 12.0, 'Customer name') extends WidgetFormField {
            public function pdfObjectContents(
                FormFieldRenderContext $context,
                int $fieldObjectId,
                array $relatedObjectIds = [],
            ): string {
                return '<< ' . implode(' ', [
                    ...$this->widgetDictionaryEntries($context, $fieldObjectId),
                    '/FT /Tx',
                ]) . ' >>';
            }
        };
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

    public function testItRendersTextAnnotationsWithExplicitOptions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textAnnotationWithOptions(
                40,
                500,
                18,
                18,
                'Kommentar',
                new TextAnnotationOptions(title: 'QA', icon: 'Help', open: true),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Text', $pdf);
        self::assertStringContainsString('/Name /Help', $pdf);
        self::assertStringContainsString('/Open true', $pdf);
    }

    public function testItRendersHighlightAnnotationsWithExplicitOptions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->highlightAnnotationWithOptions(
                40,
                500,
                80,
                10,
                new HighlightAnnotationOptions(
                    color: Color::rgb(0.9, 0.8, 0.2),
                    contents: 'Markiert',
                    title: 'QA',
                ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Highlight', $pdf);
        self::assertStringContainsString('/C [0.9 0.8 0.2]', $pdf);
        self::assertStringContainsString('/T (QA)', $pdf);
    }

    public function testItRendersFreeTextAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->freeTextAnnotation(
                'Kommentar',
                40,
                500,
                120,
                32,
                TextOptions::make(fontSize: 12, color: Color::rgb(0, 0, 0.4)),
                Color::rgb(0.2, 0.2, 0.2),
                Color::rgb(1, 1, 0.8),
                'QA',
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /FreeText', $pdf);
        self::assertStringContainsString('/DA (/', $pdf);
        self::assertStringContainsString('/IC [1 1 0.8]', $pdf);
        self::assertStringNotContainsString('/AP << /N ', $pdf);
    }

    public function testItRendersFreeTextAnnotationsWithExplicitOptions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->freeTextAnnotationWithOptions(
                'Kommentar',
                40,
                500,
                120,
                32,
                TextOptions::make(fontSize: 12),
                new FreeTextAnnotationOptions(
                    textColor: Color::rgb(0, 0, 0.4),
                    borderColor: Color::rgb(0.2, 0.2, 0.2),
                    fillColor: Color::rgb(1, 1, 0.8),
                    metadata: new AnnotationMetadata(title: 'QA'),
                ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /FreeText', $pdf);
        self::assertStringContainsString('/T (QA)', $pdf);
        self::assertStringContainsString('/C [0.2 0.2 0.2]', $pdf);
        self::assertStringContainsString('/IC [1 1 0.8]', $pdf);
    }

    public function testItRendersAdditionalAnnotationTypes(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->underlineAnnotationWithOptions(40, 500, 80, 10, new MarkupAnnotationOptions(
                color: Color::rgb(1, 0, 0),
                contents: 'Underline',
                title: 'QA',
            ))
            ->stampAnnotation(40, 470, 80, 18, 'Approved', Color::rgb(1, 0, 0), 'Stamp', 'QA')
            ->squareAnnotationWithOptions(40, 430, 80, 24, new ShapeAnnotationOptions(
                borderColor: Color::rgb(1, 0, 0),
                fillColor: Color::gray(0.9),
                borderStyle: AnnotationBorderStyle::dashed(2.0),
                contents: 'Square',
                title: 'QA',
            ))
            ->lineAnnotationWithOptions(40, 380, 120, 410, new LineAnnotationOptions(
                color: Color::rgb(0, 0, 1),
                startStyle: LineEndingStyle::CIRCLE,
                endStyle: LineEndingStyle::CLOSED_ARROW,
                borderStyle: AnnotationBorderStyle::solid(2.0),
                metadata: new AnnotationMetadata(contents: 'Line', title: 'QA', subject: 'Guide'),
            ))
            ->polygonAnnotationWithOptions([[140.0, 380.0], [180.0, 410.0], [220.0, 392.0]], new PolygonAnnotationOptions(
                borderColor: Color::rgb(1, 0, 0),
                fillColor: Color::gray(0.9),
                metadata: new AnnotationMetadata(contents: 'Polygon', title: 'QA', subject: 'Area'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Underline', $pdf);
        self::assertStringContainsString('/Subtype /Stamp', $pdf);
        self::assertStringContainsString('/Name /Approved', $pdf);
        self::assertStringContainsString('/Subtype /Square', $pdf);
        self::assertStringContainsString('/BS << /W 2 /S /D /D [3 2] >>', $pdf);
        self::assertStringContainsString('/Subtype /Line', $pdf);
        self::assertStringContainsString('/LE [/Circle /ClosedArrow]', $pdf);
        self::assertStringContainsString('/Subj (Guide)', $pdf);
        self::assertStringContainsString('/Subtype /Polygon', $pdf);
        self::assertStringContainsString('/Subj (Area)', $pdf);
    }

    public function testItRendersPopupAndFileAttachmentAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA')
            ->popupAnnotation(70, 520, 120, 60, true)
            ->fileAttachmentAnnotationWithOptions(
                'demo.txt',
                new EmbeddedFile('hello', 'text/plain'),
                40,
                460,
                12,
                14,
                new FileAttachmentAnnotationOptions(
                    description: 'Demo attachment',
                    icon: 'Graph',
                    contents: 'Anhang',
                ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Popup', $pdf);
        self::assertStringContainsString('/Open true', $pdf);
        self::assertStringContainsString('/Subtype /FileAttachment', $pdf);
        self::assertStringContainsString('/FS ', $pdf);
        self::assertStringContainsString('/Name /Graph', $pdf);
        self::assertStringContainsString('/Type /Filespec', $pdf);
        self::assertStringContainsString('/Type /EmbeddedFile', $pdf);
    }

    public function testItRendersTaggedPdfUaPageAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Text', $pdf);
        self::assertStringContainsString('/StructParent 0', $pdf);
        self::assertStringContainsString('/Type /StructElem /S /Annot', $pdf);
        self::assertStringContainsString('/Alt (Kommentar)', $pdf);
        self::assertStringContainsString('/Type /OBJR /Obj', $pdf);
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
            ->text('Open intro', TextOptions::make(
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

    public function testItRendersTopLevelOutlines(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->outline('Intro')
            ->text('Page 1')
            ->newPage()
            ->outlineAt('Details', 2, 72, 640)
            ->text('Page 2')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Outlines 8 0 R', $pdf);
        self::assertStringContainsString('<< /Type /Outlines /First 9 0 R /Last 10 0 R /Count 2 >>', $pdf);
        self::assertStringContainsString('/Title (Intro) /Parent 8 0 R /Dest [3 0 R /XYZ 0 841.89 null] /Next 10 0 R', $pdf);
        self::assertStringContainsString('/Title (Details) /Parent 8 0 R /Dest [5 0 R /XYZ 72 640 null] /Prev 9 0 R', $pdf);
    }

    public function testItRendersNestedOutlines(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->outlineClosed('Chapter 1')
            ->outlineLevel('Section 1.1', 2)
            ->outlineLevelClosed('Section 1.2', 2)
            ->outlineLevel('Subsection 1.2.1', 3)
            ->outline('Chapter 2')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('<< /Type /Outlines /First 6 0 R /Last 10 0 R /Count 2 >>', $pdf);
        self::assertStringContainsString('/Title (Chapter 1) /Parent 5 0 R /Dest [3 0 R /XYZ 0 841.89 null] /Next 10 0 R /First 7 0 R /Last 8 0 R /Count -2', $pdf);
        self::assertStringContainsString('/Title (Section 1.1) /Parent 6 0 R /Dest [3 0 R /XYZ 0 841.89 null] /Next 8 0 R', $pdf);
        self::assertStringContainsString('/Title (Section 1.2) /Parent 6 0 R /Dest [3 0 R /XYZ 0 841.89 null] /Prev 7 0 R /First 9 0 R /Last 9 0 R /Count -1', $pdf);
        self::assertStringContainsString('/Title (Subsection 1.2.1) /Parent 8 0 R /Dest [3 0 R /XYZ 0 841.89 null]', $pdf);
    }

    public function testItRendersStyledOutlineDestinationsAndGoToActions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->newPage()
            ->newPage()
            ->addOutline(
                Outline::fit('Whole Page', 1)
                    ->withStyle(new OutlineStyle()->withColor(Color::rgb(0.2, 0.4, 0.6))->withBold()->withItalic()->withAdditionalFlags(4)),
            )
            ->addOutline(Outline::fitHorizontal('Fit Horizontally', 2, 700)->asGoToAction())
            ->addOutline(Outline::fitRectangle('Fit Rectangle', 3, 40, 100, 220, 620))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Dest [3 0 R /Fit]', $pdf);
        self::assertStringContainsString('/A << /S /GoTo /D [5 0 R /FitH 700] >>', $pdf);
        self::assertStringContainsString('/Dest [7 0 R /FitR 40 100 220 620]', $pdf);
        self::assertStringContainsString('/C [0.2 0.4 0.6]', $pdf);
        self::assertStringContainsString('/F 7', $pdf);
    }

    public function testItRendersNamedAndRemoteOutlineDestinations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->namedDestination('intro')
            ->addOutline(Outline::named('Intro Bookmark', 'intro', 1))
            ->newPage()
            ->newPage()
            ->addOutline(
                Outline::named('Remote Intro', 'chapter-1', 3)
                    ->withDestination(Outline::named('Remote Intro', 'chapter-1', 3)->destination->asRemoteGoTo('external.pdf', true)),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Dest /intro', $pdf);
        self::assertStringContainsString('/A << /S /GoToR /F (external.pdf) /D /chapter-1 /NewWindow true >>', $pdf);
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
            ->text('Read more', TextOptions::make(
                link: LinkTarget::externalUrl('https://example.com'),
            ))
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/P << /MCID 0 >> BDC', $pdf);
        self::assertStringContainsString('/Link << /MCID 1 >> BDC', $pdf);
        self::assertStringContainsString('/StructParent 1', $pdf);
        self::assertStringContainsString('/K [1 << /Type /OBJR /Obj', $pdf);
    }

    public function testItRendersMultipleTextSegmentLinks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text([
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
            ->text([
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
            ->text([
                new TextSegment('Read docs', LinkTarget::externalUrl('https://example.com/docs')),
            ], TextOptions::make(width: 45))
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
            ->text([
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

    public function testItRendersGroupedRectLinksWithExplicitOptions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->linkWithOptions(
                'https://example.com/docs',
                40,
                500,
                120,
                16,
                new LinkAnnotationOptions(
                    contents: 'Open docs section',
                    accessibleLabel: 'Read the documentation section',
                    groupKey: 'docs-link',
                ),
            )
            ->linkWithOptions(
                'https://example.com/docs',
                40,
                470,
                120,
                16,
                new LinkAnnotationOptions(
                    contents: 'Open docs section',
                    accessibleLabel: 'Read the documentation section',
                    groupKey: 'docs-link',
                ),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertSame(2, substr_count($pdf, '/Subtype /Link'));
        self::assertSame(1, substr_count($pdf, '/Type /StructElem /S /Link'));
        self::assertStringContainsString('/Alt (Read the documentation section)', $pdf);
    }

    public function testItRendersTaggedPdfUaTablesWithCaptionHeaderAndCells(): void
    {
        $table = Table::define(
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
            TableColumn::fixed(90.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withPlacement(new TablePlacement(24.0, 270.0))
                    ->withCaption(TableCaption::text('Quarterly summary')),
            )
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
            ->text('PDF/A-1b Regression Привет', TextOptions::make(
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
            ->text('PDF/A-2u Regression Привет', TextOptions::make(
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
            ->text('PDF/A-2u Link Regression Привет', TextOptions::make(
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
            ->text('PDF/A-2u Kommentar Regression Привет', TextOptions::make(
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

    public function testItRendersPdfA2aTaggedTextAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2a())
            ->title('PDF/A-2a Text Annotation Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2a text annotation regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->text('PDF/A-2a Kommentar Regression Привет', TextOptions::make(
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
        self::assertStringContainsString('/Type /StructElem /S /Annot', $pdf);
        self::assertStringContainsString('/Alt (Kommentar)', $pdf);
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
            ->text('PDF/A-2u Highlight Regression Привет', TextOptions::make(
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

    public function testItRendersPdfA2uFreeTextAnnotationsWithAppearanceStreams(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u FreeText Annotation Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u free text annotation regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->freeTextAnnotation(
                'Kommentar Привет',
                72,
                680,
                180,
                40,
                TextOptions::make(
                    fontSize: 12,
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    color: Color::rgb(0, 0, 0.4),
                ),
                Color::rgb(0.2, 0.2, 0.2),
                Color::rgb(1, 1, 0.8),
                'QA',
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /FreeText', $pdf);
        self::assertStringContainsString('/DA (/', $pdf);
        self::assertStringContainsString('/AP << /N ', $pdf);
        self::assertStringContainsString('/Subtype /Form /FormType 1 /BBox [0 0 180 40]', $pdf);
        self::assertStringContainsString('/Resources << /Font << /', $pdf);
        self::assertStringContainsString('/F 4', $pdf);
    }

    public function testItRendersPdfA3aTaggedChoiceFields(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3a())
            ->title('PDF/A-3a Tagged Form Regression')
            ->language('de-DE')
            ->comboBox('status', 72, 680, 140, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->listBox('skills', 72, 620, 140, 44, ['php' => 'PHP', 'pdf' => 'PDF'], ['php'], 'Skills')
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertSame(2, substr_count($pdf, '/Type /StructElem /S /Form'));
        self::assertStringContainsString('/Alt (Status)', $pdf);
        self::assertStringContainsString('/Alt (Skills)', $pdf);
        self::assertStringNotContainsString('/Helv', $pdf);
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
            ->text('Einleitung Привет', TextOptions::make(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->newPage()
            ->text('Linkseite Привет', TextOptions::make(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->linkToPage(1, 72, 680, 180, 16, 'Back To Page One')
            ->linkToPagePosition(1, 72, 760, 72, 650, 180, 16, 'Back To Heading')
            ->text('Zur Einleitung Привет', TextOptions::make(
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
            ->text('PDF/A-2u Bild Regression Привет', TextOptions::make(
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

    public function testItRendersPdfA2uCcittImages(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u CCITT Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u CCITT image regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('DocumentRendererTest')
            ->text('PDF/A-2u CCITT Bild Regression Привет', TextOptions::make(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.08, 0.16, 0.35),
            ))
            ->image(
                ImageSource::monochromeCcitt([
                    '11111111',
                    '00000000',
                ]),
                ImagePlacement::at(72, 610, width: 160),
            )
            ->build();

        $renderer = new DocumentRenderer();
        $output = new StringOutput();

        $renderer->write($document, $output);

        $pdf = $output->contents();

        self::assertStringContainsString('/Subtype /Image', $pdf);
        self::assertStringContainsString('/ColorSpace /DeviceGray', $pdf);
        self::assertStringContainsString('/BitsPerComponent 1', $pdf);
        self::assertStringContainsString('/Filter /CCITTFaxDecode', $pdf);
        self::assertStringContainsString('/DecodeParms << /K 0 /Columns 8 /Rows 2 /BlackIs1 true /EndOfLine true >>', $pdf);
    }

    public function testItRendersCmykJpegImagesWithAdobeDecodeArray(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-cmyk-jpeg-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary CMYK JPEG fixture path.');
        }

        file_put_contents($path, JpegFixture::tinyCmykJpegBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(72, 610, width: 160))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceCMYK', $pdf);
            self::assertStringContainsString('/Filter /DCTDecode', $pdf);
            self::assertStringContainsString('/Decode [1 0 1 0 1 0 1 0]', $pdf);
        } finally {
            unlink($path);
        }
    }

    public function testItRendersPdfA2uTiffPredictorAndMultiStripCcittImages(): void
    {
        $rgbPath = tempnam(sys_get_temp_dir(), 'pdf2-pdfa2u-rgb-tiff-');
        $ccittPath = tempnam(sys_get_temp_dir(), 'pdf2-pdfa2u-ccitt-tiff-');

        if ($rgbPath === false || $ccittPath === false) {
            self::fail('Unable to allocate temporary TIFF fixture paths.');
        }

        file_put_contents($rgbPath, TiffFixture::tinyPredictorDeflateRgbTiffBytes());
        file_put_contents($ccittPath, TiffFixture::tinyMultiStripCcittGroup3TiffBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->profile(Profile::pdfA2u())
                ->title('PDF/A-2u TIFF Regression')
                ->author('kalle/pdf2')
                ->subject('PDF/A-2u TIFF predictor and CCITT regression fixture')
                ->language('de-DE')
                ->creator('Regression Fixture')
                ->creatorTool('DocumentRendererTest')
                ->text('PDF/A-2u TIFF Regression Привет', TextOptions::make(
                    x: 72,
                    y: 760,
                    fontSize: 18,
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    color: Color::rgb(0.08, 0.16, 0.35),
                ))
                ->imageFile($rgbPath, ImagePlacement::at(72, 610, width: 120))
                ->imageFile($ccittPath, ImagePlacement::at(220, 610, width: 120))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceGray', $pdf);
            self::assertStringContainsString('/Filter /CCITTFaxDecode', $pdf);
            self::assertStringContainsString('/EndOfBlock false', $pdf);
        } finally {
            unlink($rgbPath);
            unlink($ccittPath);
        }
    }

    public function testItRendersMixedTiffFixturesEndToEnd(): void
    {
        $grayPath = tempnam(sys_get_temp_dir(), 'pdf2-tiff-gray-');
        $rgbPath = tempnam(sys_get_temp_dir(), 'pdf2-tiff-rgb-');
        $ccittPath = tempnam(sys_get_temp_dir(), 'pdf2-tiff-ccitt-');

        if ($grayPath === false || $rgbPath === false || $ccittPath === false) {
            self::fail('Unable to allocate temporary TIFF fixture paths.');
        }

        file_put_contents($grayPath, TiffFixture::tinyPredictorLzwGrayscaleTiffBytes());
        file_put_contents($rgbPath, TiffFixture::tinyPredictorDeflateRgbTiffBytes());
        file_put_contents($ccittPath, TiffFixture::tinyMultiStripCcittGroup3TiffBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->title('TIFF Interop Regression')
                ->imageFile($grayPath, ImagePlacement::at(40, 650, width: 80))
                ->imageFile($rgbPath, ImagePlacement::at(140, 650, width: 80))
                ->imageFile($ccittPath, ImagePlacement::at(240, 650, width: 80))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceGray', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
            self::assertStringContainsString('/Filter /CCITTFaxDecode', $pdf);
            self::assertStringContainsString('/EndOfBlock false', $pdf);
        } finally {
            unlink($grayPath);
            unlink($rgbPath);
            unlink($ccittPath);
        }
    }

    public function testItRendersStableImageObjectDictionariesForMixedTiffImports(): void
    {
        $grayPath = tempnam(sys_get_temp_dir(), 'pdf2-tiff-gray-');
        $rgbPath = tempnam(sys_get_temp_dir(), 'pdf2-tiff-rgb-');
        $ccittPath = tempnam(sys_get_temp_dir(), 'pdf2-tiff-ccitt-');

        if ($grayPath === false || $rgbPath === false || $ccittPath === false) {
            self::fail('Unable to allocate temporary TIFF fixture paths.');
        }

        file_put_contents($grayPath, TiffFixture::tinyPredictorLzwGrayscaleTiffBytes());
        file_put_contents($rgbPath, TiffFixture::tinyPredictorDeflateRgbTiffBytes());
        file_put_contents($ccittPath, TiffFixture::tinyMultiStripCcittGroup3TiffBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->title('TIFF Golden Regression')
                ->imageFile($grayPath, ImagePlacement::at(40, 650, width: 80))
                ->imageFile($rgbPath, ImagePlacement::at(140, 650, width: 80))
                ->imageFile($ccittPath, ImagePlacement::at(240, 650, width: 80))
                ->build();

            $output = new StringOutput();
            (new DocumentRenderer())->write($document, $output);

            self::assertSame([
                '<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /RunLengthDecode /Length 4 >>',
                '<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /RunLengthDecode /Length 8 >>',
                '<< /Type /XObject /Subtype /Image /Width 8 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 8 /Rows 2 /BlackIs1 true /EndOfLine true /EndOfBlock false >> /Length 25 >>',
            ], $this->renderedImageDictionaries($output->contents()));
        } finally {
            unlink($grayPath);
            unlink($rgbPath);
            unlink($ccittPath);
        }
    }

    public function testItRendersImportedCmykTiffImages(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-tiff-cmyk-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary CMYK TIFF fixture path.');
        }

        file_put_contents($path, TiffFixture::tinyPredictorDeflateCmykTiffBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceCMYK', $pdf);
            self::assertStringContainsString('/BitsPerComponent 8', $pdf);
        } finally {
            unlink($path);
        }
    }

    public function testItRendersImportedGifImagesWithSoftMasks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-gif-render-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary GIF fixture path.');
        }

        file_put_contents($path, GifFixture::tinyTransparentGifBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000000000>]', $pdf);
            self::assertStringContainsString('/SMask ', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceGray', $pdf);
        } finally {
            unlink($path);
        }
    }

    public function testItRendersImportedBmpImagesWithSoftMasks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-bmp-render-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary BMP fixture path.');
        }

        file_put_contents($path, BmpFixture::tiny32BitRgbaBmpBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
            self::assertStringContainsString('/BitsPerComponent 8', $pdf);
            self::assertStringContainsString('/SMask ', $pdf);
        } finally {
            unlink($path);
        }
    }

    public function testItRendersStableImageObjectDictionariesForTransparentImports(): void
    {
        $gifPath = tempnam(sys_get_temp_dir(), 'pdf2-gif-render-');
        $bmpPath = tempnam(sys_get_temp_dir(), 'pdf2-bmp-render-');

        if ($gifPath === false || $bmpPath === false) {
            self::fail('Unable to allocate a temporary transparent image fixture path.');
        }

        file_put_contents($gifPath, GifFixture::tinyTransparentGifBytes());
        file_put_contents($bmpPath, BmpFixture::tiny32BitRgbaBmpBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->title('Transparent Golden Regression')
                ->imageFile($gifPath, ImagePlacement::at(40, 650, width: 80))
                ->imageFile($bmpPath, ImagePlacement::at(140, 650, width: 80))
                ->build();

            $output = new StringOutput();
            (new DocumentRenderer())->write($document, $output);

            self::assertSame([
                '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/Indexed /DeviceRGB 1 <000000000000>] /BitsPerComponent 8 /Filter /FlateDecode /SMask 6 0 R /Length 9 >>',
                '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length 9 >>',
                '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /RunLengthDecode /SMask 8 0 R /Length 5 >>',
                '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length 9 >>',
            ], $this->renderedImageDictionaries($output->contents()));
        } finally {
            unlink($gifPath);
            unlink($bmpPath);
        }
    }

    public function testItRendersImportedPaletteTiffImages(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-tiff-palette-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary palette TIFF fixture path.');
        }

        file_put_contents($path, TiffFixture::tinyUncompressedPaletteTiffBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000FF00FF>]', $pdf);
            self::assertStringNotContainsString('/SMask ', $pdf);
        } finally {
            unlink($path);
        }
    }

    public function testItRendersImportedCompressedPaletteTiffImages(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-tiff-palette-compressed-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary compressed palette TIFF fixture path.');
        }

        file_put_contents($path, TiffFixture::tinyDeflatePaletteTiffBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000FF00FF>]', $pdf);
        } finally {
            unlink($path);
        }
    }

    public function testItRendersImportedBitfieldsBmpImages(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-bmp-bitfields-render-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary bitfields BMP fixture path.');
        }

        file_put_contents($path, BmpFixture::tiny32BitBitfieldsReversedBmpBytes());

        try {
            $document = DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                ->build();

            $renderer = new DocumentRenderer();
            $output = new StringOutput();

            $renderer->write($document, $output);

            $pdf = $output->contents();

            self::assertStringContainsString('/Subtype /Image', $pdf);
            self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
            self::assertStringContainsString('/SMask ', $pdf);
        } finally {
            unlink($path);
        }
    }

    public function testItRendersImportedOpaqueWebpImages(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-webp-render-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary WebP fixture path.');
        }

        file_put_contents(
            $path,
            (\function_exists('gd_info') && ((\gd_info()['WebP Support'] ?? false) === true))
                ? WebpFixture::tinyOpaqueWebpBytes()
                : WebpFixture::tinyWebpBytes(),
        );

        try {
            if (\function_exists('gd_info') && ((\gd_info()['WebP Support'] ?? false) === true)) {
                $document = DefaultDocumentBuilder::make()
                    ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                    ->build();

                $renderer = new DocumentRenderer();
                $output = new StringOutput();

                $renderer->write($document, $output);

                $pdf = $output->contents();

                self::assertStringContainsString('/Subtype /Image', $pdf);
                self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
                self::assertStringContainsString('/BitsPerComponent 8', $pdf);
                self::assertStringNotContainsString('/SMask ', $pdf);

                return;
            }

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf(
                "WEBP image '%s' requires GD WebP runtime support, which is not available.",
                $path,
            ));

            DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                ->build();
        } finally {
            unlink($path);
        }
    }

    public function testItRendersImportedTransparentWebpImagesWithSoftMasks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-webp-alpha-render-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary transparent WebP fixture path.');
        }

        file_put_contents(
            $path,
            (\function_exists('gd_info') && ((\gd_info()['WebP Support'] ?? false) === true))
                ? WebpFixture::tinyTransparentWebpBytes()
                : WebpFixture::tinyWebpBytes(),
        );

        try {
            if (\function_exists('gd_info') && ((\gd_info()['WebP Support'] ?? false) === true)) {
                $document = DefaultDocumentBuilder::make()
                    ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                    ->build();

                $renderer = new DocumentRenderer();
                $output = new StringOutput();

                $renderer->write($document, $output);

                $pdf = $output->contents();

                self::assertStringContainsString('/Subtype /Image', $pdf);
                self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
                self::assertStringContainsString('/BitsPerComponent 8', $pdf);
                self::assertStringContainsString('/SMask ', $pdf);
                self::assertStringContainsString('/ColorSpace /DeviceGray', $pdf);

                return;
            }

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf(
                "WEBP image '%s' requires GD WebP runtime support, which is not available.",
                $path,
            ));

            DefaultDocumentBuilder::make()
                ->imageFile($path, ImagePlacement::at(40, 650, width: 80))
                ->build();
        } finally {
            unlink($path);
        }
    }

    /**
     * @return list<string>
     */
    private function renderedImageDictionaries(string $pdf): array
    {
        preg_match_all(
            '~\n\d+ 0 obj\n(<< /Type /XObject /Subtype /Image .* >>)\nstream\n~sU',
            $pdf,
            $matches,
        );

        /** @var list<string> $dictionaries */
        $dictionaries = $matches[1];

        return $dictionaries;
    }
}
