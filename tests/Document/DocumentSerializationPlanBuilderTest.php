<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

use function preg_match;

final class DocumentSerializationPlanBuilderTest extends TestCase
{
    public function testItBuildsAMinimalSerializationPlan(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(Profile::standard(Version::V1_7));

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(Version::V1_7, $plan->fileStructure->version);
        self::assertSame(5, $plan->fileStructure->trailer->size);
        self::assertSame(1, $plan->fileStructure->trailer->rootObjectId);
        self::assertCount(4, $objects);
        self::assertSame(1, $objects[0]->objectId);
        self::assertSame('<< /Type /Catalog /Pages 2 0 R >>', $objects[0]->contents);
        self::assertSame(2, $objects[1]->objectId);
        self::assertSame('<< /Type /Pages /Count 1 /Kids [3 0 R] >>', $objects[1]->contents);
        self::assertSame(3, $objects[2]->objectId);
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R >>', $objects[2]->contents);
        self::assertSame(4, $objects[3]->objectId);
        self::assertSame("<< /Length 0 >>\nstream\nendstream", $objects[3]->contents);
    }

    public function testItAddsAnInfoObjectWhenDocumentMetadataIsPresent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::standard(),
            title: 'Example Title',
            author: 'Sebastian Kalicki',
            subject: 'Example Subject',
            creator: 'Kalle PDF',
            creatorTool: 'pdf2 test suite',
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(7, $plan->fileStructure->trailer->size);
        self::assertSame(6, $plan->fileStructure->trailer->infoObjectId);
        self::assertCount(6, $objects);
        self::assertSame('<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>', $objects[0]->contents);
        self::assertSame(5, $objects[4]->objectId);
        self::assertStringStartsWith('<< /Type /Metadata /Subtype /XML /Length ', $objects[4]->contents);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Example Title</rdf:li>', $objects[4]->contents);
        self::assertStringContainsString('<pdf:Producer>pdf2 test suite</pdf:Producer>', $objects[4]->contents);
        self::assertMatchesRegularExpression('/<xmp:CreateDate>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}<\/xmp:CreateDate>/', $objects[4]->contents);
        self::assertSame(6, $objects[5]->objectId);
        self::assertStringStartsWith(
            '<< /Title (Example Title) /Author (Sebastian Kalicki) /Subject (Example Subject) /Creator (Kalle PDF) /Producer (pdf2 test suite) /CreationDate (',
            $objects[5]->contents,
        );
        self::assertStringContainsString('/ModDate (', $objects[5]->contents);
    }

    public function testItBuildsPageObjectsForAllDocumentPages(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            pages: [
                new Page(PageSize::A4(), "q\nQ"),
                new Page(PageSize::A5()),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(7, $plan->fileStructure->trailer->size);
        self::assertCount(6, $objects);
        self::assertSame('<< /Type /Pages /Count 2 /Kids [3 0 R 5 0 R] >>', $objects[1]->contents);
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R >>', $objects[2]->contents);
        self::assertSame("<< /Length 4 >>\nstream\nq\nQ\nendstream", $objects[3]->contents);
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 419.528 595.276] /Resources << >> /Contents 6 0 R >>', $objects[4]->contents);
        self::assertSame("<< /Length 0 >>\nstream\nendstream", $objects[5]->contents);
    }

    public function testItKeepsMultiplePagesBuiltThroughTheBuilder(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Page 1')
            ->newPage()
            ->text('Page 2')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame('<< /Type /Pages /Count 2 /Kids [3 0 R 5 0 R] >>', $objects[1]->contents);
        self::assertStringContainsString('[<50>', $objects[3]->contents);
        self::assertStringContainsString('[<50>', $objects[5]->contents);
        self::assertStringContainsString('] TJ', $objects[3]->contents);
        self::assertStringContainsString('] TJ', $objects[5]->contents);
    }

    public function testItPrependsBackgroundDrawingCommandsToPageContents(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Cover')
            ->newPage(new PageOptions(
                pageSize: PageSize::A5(),
                orientation: PageOrientation::LANDSCAPE,
                margin: Margin::all(24.0),
                backgroundColor: Color::hex('#f5f5f5'),
            ))
            ->text('Appendix')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString("0.961 0.961 0.961 rg\n0 0 595.276 419.528 re\nf\nQ", $objects[5]->contents);
        self::assertStringContainsString('(Appendix) Tj', $objects[5]->contents);
    }

    public function testItUsesTheCorrectPdfColorOperatorForGrayAndCmykBackgrounds(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            pages: [
                new Page(PageSize::A4(), backgroundColor: Color::gray(0.5)),
                new Page(PageSize::A4(), backgroundColor: Color::cmyk(0.1, 0.2, 0.3, 0.4)),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString("0.5 g\n0 0 595.276 841.89 re\nf\nQ", $objects[3]->contents);
        self::assertStringContainsString("0.1 0.2 0.3 0.4 k\n0 0 595.276 841.89 re\nf\nQ", $objects[5]->contents);
    }

    public function testItAddsEncodingToStandardFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Hello', new TextOptions(fontName: StandardFont::HELVETICA->value))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', $objects[4]->contents);
    }

    public function testItAddsWesternDifferencesEncodingForPdf10StandardFonts(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdf10())
            ->text('ÄÖÜäöüß', new TextOptions(fontName: StandardFont::HELVETICA->value))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringStartsWith(
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding << /Type /Encoding /BaseEncoding /StandardEncoding /Differences [128 /Adieresis',
            $objects[4]->contents,
        );
    }

    public function testItAddsIsoLatin1EncodingToExplicitlyConfiguredStandardFonts(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdf10(),
            pages: [
                new Page(
                    PageSize::A4(),
                    fontResources: [
                        'F1' => new PageFont(StandardFont::HELVETICA->value, StandardFontEncoding::ISO_LATIN_1),
                    ],
                ),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /ISOLatin1Encoding >>',
            $objects[4]->contents,
        );
    }

    public function testItAddsCoreFontGlyphDifferencesWhenExplicitGlyphNamesNeedThem(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->glyphs(StandardFontGlyphRun::fromGlyphNames(StandardFont::HELVETICA, [
                'A',
                'Euro',
                'Aogonek',
            ]))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/BaseEncoding /WinAnsiEncoding', $objects[4]->contents);
        self::assertStringContainsString('/Differences [128 /Euro /Aogonek]', $objects[4]->contents);
    }

    public function testItAddsImageXObjectsToPageResources(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(40, 500, width: 120),
                ImageAccessibility::alternativeText('Example image'),
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << /XObject << /Im1 5 0 R >> >> /Contents 4 0 R >>',
            $objects[2]->contents,
        );
        self::assertStringContainsString('/Subtype /Image', $objects[4]->contents);
        self::assertStringContainsString('/Filter /DCTDecode', $objects[4]->contents);
    }

    public function testItBuildsSoftMaskImageObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->image(
                ImageSource::flate(
                    'rgb-data',
                    2,
                    1,
                    ImageColorSpace::RGB,
                    softMask: ImageSource::alphaMask('alpha-data', 2, 1),
                ),
                ImagePlacement::at(10, 20),
                ImageAccessibility::alternativeText('Transparent image'),
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/XObject << /Im1 5 0 R >>', $objects[2]->contents);
        self::assertStringContainsString('/SMask 6 0 R', $objects[4]->contents);
        self::assertStringContainsString('/ColorSpace /DeviceGray', $objects[5]->contents);
    }

    public function testItRejectsPdfUaImagesWithoutAccessibilityMetadata(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->image(ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB), ImagePlacement::at(40, 500, width: 120))
            ->build();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tagged PDF profiles require accessibility metadata for image 1 on page 1.');

        $builder->build($document);
    }

    public function testItAddsCatalogMetadataLangAndMarkInfoForTaggedProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible Copy',
            language: 'de-DE',
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R /Lang (de-DE) /MarkInfo << /Marked true >> /StructTreeRoot 5 0 R >>',
            $objects[0]->contents,
        );
        self::assertSame('<< /Type /StructTreeRoot /K [6 0 R] >>', $objects[4]->contents);
        self::assertSame('<< /Type /StructElem /S /Document /P 5 0 R /K [] >>', $objects[5]->contents);
        self::assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $objects[6]->contents);
    }

    public function testItAddsPdfAOutputIntentAndIdentificationMetadata(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'Archive Copy',
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/Metadata 5 0 R', $objects[0]->contents);
        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent /S /GTS_PDFA1', $objects[0]->contents);
        self::assertStringContainsString('/DestOutputProfile 6 0 R', $objects[0]->contents);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $objects[4]->contents);
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $objects[4]->contents);
        self::assertStringStartsWith('<< /N 3 /Length ', $objects[5]->contents);
    }

    public function testItUsesACustomPdfAOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $path = tempnam(sys_get_temp_dir(), 'pdf2-icc-');
        self::assertNotFalse($path);
        file_put_contents($path, 'ICC');

        try {
            $document = new Document(
                profile: Profile::pdfA2u(),
                title: 'Archive Copy',
                pdfaOutputIntent: new PdfAOutputIntent($path, 'Custom RGB', 'Custom profile', 4),
            );

            $plan = $builder->build($document);
            $objects = iterator_to_array($plan->objects);

            self::assertStringContainsString('/OutputConditionIdentifier (Custom RGB)', $objects[0]->contents);
            self::assertStringContainsString('/Info (Custom profile)', $objects[0]->contents);
            self::assertSame("<< /N 4 /Length 3 >>\nstream\nICC\nendstream", $objects[5]->contents);
        } finally {
            @unlink($path);
        }
    }

    public function testItRejectsTaggedProfilesWithoutLanguage(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible Copy',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires a document language.');

        $builder->build($document);
    }

    public function testItRejectsStandardFontsForPdfAProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('Archive Copy')
            ->text('Hello')
            ->build();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u requires embedded fonts. Found standard font "Helvetica" on page 1.');

        $builder->build($document);
    }

    public function testItRejectsSoftMaskTransparencyForPdfA1Profiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->image(
                ImageSource::flate(
                    'rgb-data',
                    2,
                    1,
                    ImageColorSpace::RGB,
                    softMask: ImageSource::alphaMask('alpha-data', 2, 1),
                ),
                ImagePlacement::at(10, 20),
                ImageAccessibility::alternativeText('Transparent image'),
            )
            ->build();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow soft-mask image transparency for image resource 1 on page 1.');

        $builder->build($document);
    }

    public function testItRejectsTaggedProfilesWithoutTitle(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfUa1(),
            language: 'de-DE',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires a document title.');

        $builder->build($document);
    }

    public function testItAllowsDecorativeImagesForPdfUaProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(40, 500, width: 120),
                ImageAccessibility::decorative(),
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/XObject << /Im1 5 0 R >>', $objects[2]->contents);
        self::assertStringContainsString('/Artifact BMC', $objects[3]->contents);
        self::assertStringContainsString('/StructTreeRoot 6 0 R', $objects[0]->contents);
        self::assertSame('<< /Type /StructTreeRoot /K [7 0 R] >>', $objects[5]->contents);
        self::assertSame('<< /Type /StructElem /S /Document /P 6 0 R /K [] >>', $objects[6]->contents);
    }

    public function testItBuildsTaggedFigureStructureForPdfUaImages(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
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

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/StructTreeRoot 6 0 R', $objects[0]->contents);
        self::assertStringContainsString('/StructParents 0', $objects[2]->contents);
        self::assertStringContainsString('/Figure << /MCID 0 >> BDC', $objects[3]->contents);
        self::assertSame('<< /Type /StructTreeRoot /K [7 0 R] /ParentTree 8 0 R >>', $objects[5]->contents);
        self::assertSame('<< /Type /StructElem /S /Document /P 6 0 R /K [9 0 R] >>', $objects[6]->contents);
        self::assertSame('<< /Nums [0 [9 0 R]] >>', $objects[7]->contents);
        self::assertSame('<< /Type /StructElem /S /Figure /P 7 0 R /Pg 3 0 R /Alt (Logo) /K 0 >>', $objects[8]->contents);
    }

    public function testItBuildsEmbeddedTrueTypeFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('A', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /TrueType', $serialized);
        self::assertStringContainsString('/FontDescriptor', $serialized);
        self::assertStringContainsString('/FontFile2', $serialized);
        self::assertStringContainsString('/BaseFont /TestFont-Regular', $serialized);
    }

    public function testItBuildsEmbeddedCffFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('A', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalCffOpenTypeFontBytes()),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Type1', $serialized);
        self::assertStringContainsString('/FontDescriptor', $serialized);
        self::assertStringContainsString('/FontFile3', $serialized);
        self::assertStringContainsString('/Subtype /OpenType', $serialized);
        self::assertStringContainsString('/BaseFont /TestCff-Regular', $serialized);
    }

    public function testItBuildsUnicodeEmbeddedTrueTypeFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
            ))
            ->newPage()
            ->text('中😀', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Type0', $serialized);
        self::assertStringContainsString('/Subtype /CIDFontType2', $serialized);
        self::assertStringContainsString('/Encoding /Identity-H', $serialized);
        self::assertStringContainsString('/CIDToGIDMap', $serialized);
        self::assertStringContainsString('/ToUnicode', $serialized);
        self::assertMatchesRegularExpression('/\\/BaseFont \\/[A-Z]{6}\\+TestFont-Regular/', $serialized);
        self::assertStringContainsString('<0001> <0416>', $serialized);
        self::assertStringContainsString('<0001> <4E2D>', $serialized);
        self::assertStringContainsString('<0002> <D83DDE00>', $serialized);
        preg_match('/\\/Length1 ([0-9]+)/', $serialized, $matches);
        self::assertArrayHasKey(1, $matches);
        self::assertLessThan(strlen(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()), (int) $matches[1]);
    }

    public function testItBuildsUnicodeEmbeddedCffFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()),
            ))
            ->text('中😀', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Type0', $serialized);
        self::assertStringContainsString('/Subtype /CIDFontType0', $serialized);
        self::assertStringContainsString('/Encoding /Identity-H', $serialized);
        self::assertStringContainsString('/FontFile3', $serialized);
        self::assertStringContainsString('/Subtype /OpenType', $serialized);
        self::assertMatchesRegularExpression('/\\/BaseFont \\/[A-Z]{6}\\+TestCff-Regular/', $serialized);
        self::assertStringNotContainsString('/CIDToGIDMap', $serialized);
        self::assertStringContainsString('<0001> <0416>', $serialized);
        self::assertStringContainsString('<0002> <4E2D>', $serialized);
        self::assertStringContainsString('<0003> <D83DDE00>', $serialized);
        preg_match('/<< \\/Length ([0-9]+) \\/Subtype \\/OpenType >>/', $serialized, $matches);
        self::assertArrayHasKey(1, $matches);
        self::assertLessThan(strlen(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()), (int) $matches[1]);
    }
}
