<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function array_map;
use function array_values;
use function dirname;
use function iterator_to_array;
use function preg_replace;

use DateTimeImmutable;
use InvalidArgumentException;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentMetadataObjectBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuildState;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentTaggedPdfObjectBuilder;
use Kalle\Pdf\Document\OptionalContentConfiguration;
use Kalle\Pdf\Document\PdfAObjectGraphValidator;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Page\OptionalContentGroup;
use Kalle\Pdf\Page\OptionalContentMembership;
use Kalle\Pdf\Page\OptionalContentVisibilityExpression;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\RichMediaAnnotation;
use Kalle\Pdf\Page\RichMediaAssetType;
use Kalle\Pdf\Page\RichMediaPresentationStyle;
use Kalle\Pdf\Page\ThreeDAnnotation;
use Kalle\Pdf\Page\ThreeDAssetType;
use Kalle\Pdf\Page\ThreeDViewPreset;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\IndirectObject;
use PHPUnit\Framework\TestCase;

final class PdfAObjectGraphValidatorTest extends TestCase
{
    public function testItRejectsPdfA2uCatalogsWithoutMetadataReferences(): void
    {
        $document = $this->pdfA2uDocument();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);

        self::assertNotNull($state->metadataObjectId);

        $objects = array_map(
            static function (IndirectObject $object) use ($state): IndirectObject {
                if ($object->objectId !== 1) {
                    return $object;
                }

                $tamperedContents = preg_replace(
                    '/\s*\/Metadata\s+' . $state->metadataObjectId . '\s+0\s+R/',
                    '',
                    $object->contents,
                    1,
                );

                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF/A catalog must reference the metadata stream.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA2uCatalogsWithoutOutputIntentArrays(): void
    {
        $document = $this->pdfA2uDocument();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);

        $objects = array_map(
            static function (IndirectObject $object): IndirectObject {
                if ($object->objectId !== 1) {
                    return $object;
                }

                $tamperedContents = preg_replace('/\s*\/OutputIntents\s+\[[^\]]+\]/', '', $object->contents, 1);
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF/A catalog must serialize an OutputIntents array.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA3bCatalogsWithoutAssociatedFileReferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3b())
            ->title('Archive Package')
            ->attachment(
                'data.xml',
                '<root/>',
                'Source data',
                'application/xml',
                AssociatedFileRelationship::SOURCE,
            )
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);

        $objects = array_map(
            static function (IndirectObject $object): IndirectObject {
                if ($object->objectId !== 1) {
                    return $object;
                }

                $tamperedContents = preg_replace('/\s*\/AF\s+\[[^\]]+\]/', '', $object->contents, 1);
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF/A catalog must serialize an /AF array for associated files.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA2uAnnotationObjectsWithoutAppearanceReferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Text Annotation Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u text annotation regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('PdfAObjectGraphValidatorTest')
            ->textAnnotation(72, 680, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];
        $appearanceObjectId = $state->pageAnnotationAppearanceObjectIds[0][0];

        self::assertNotNull($appearanceObjectId);

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId, $appearanceObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace(
                    '/\s*\/AP\s*<<\s*\/N\s+' . $appearanceObjectId . '\s+0\s+R\s*>>/',
                    '',
                    $object->contents,
                    1,
                );

                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'PDF/A requires page annotation 1 on page 1 to serialize /AP << /N %d 0 R >>.',
            $appearanceObjectId,
        ));

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA2uLinkAnnotationsWithoutUriActions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Link Annotation Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u link annotation regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('PdfAObjectGraphValidatorTest')
            ->link('https://example.com/spec', 72, 670, 180, 16, 'Specification Link')
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace('/\s*\/A\s*<<\s*\/S\s*\/URI\s*\/URI\s*\([^)]+\)\s*>>/', '', $object->contents, 1);
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2u requires external link annotation 1 on page 1 to serialize a URI action in the final PDF/A object graph.',
        );

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA2uAnnotationObjectsWithUnsupportedSerializedSubtypes(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Text Annotation Regression')
            ->author('kalle/pdf2')
            ->subject('PDF/A-2u text annotation regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('PdfAObjectGraphValidatorTest')
            ->textAnnotation(72, 680, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace('/\/Subtype\s*\/Text/', '/Subtype /Square', $object->contents, 1);
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2u requires page annotation 1 on page 1 to serialize /Subtype /Text in the final PDF/A object graph.',
        );

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA2aAcroFormCatalogsWithoutAcroFormReferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2a())
            ->title('Archive Form')
            ->language('de-DE')
            ->comboBox('status', 40, 500, 120, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);

        self::assertNotNull($state->acroFormObjectId);

        $objects = array_map(
            static function (IndirectObject $object) use ($state): IndirectObject {
                if ($object->objectId !== 1) {
                    return $object;
                }

                $tamperedContents = preg_replace(
                    '/\s*\/AcroForm\s+' . $state->acroFormObjectId . '\s+0\s+R/',
                    '',
                    $object->contents,
                    1,
                );

                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF/A catalog must reference the AcroForm object.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA3bAttachmentObjectsWithoutAfRelationship(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3b())
            ->title('Archive Package')
            ->attachment(
                'data.xml',
                '<root/>',
                'Source data',
                'application/xml',
                AssociatedFileRelationship::SOURCE,
            )
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $attachmentObjectId = $state->attachmentObjectIds[0];

        $objects = array_map(
            static function (IndirectObject $object) use ($attachmentObjectId): IndirectObject {
                if ($object->objectId !== $attachmentObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace('/\s*\/AFRelationship\s+\/Source/', '', $object->contents, 1);
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF/A attachment object 1 must serialize /AFRelationship /Source.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA3bAttachmentObjectsWithoutEfReferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3b())
            ->title('Archive Package')
            ->attachment(
                'data.xml',
                '<root/>',
                'Source data',
                'application/xml',
                AssociatedFileRelationship::SOURCE,
            )
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $attachmentObjectId = $state->attachmentObjectIds[0];
        $embeddedFileObjectId = $state->embeddedFileObjectIds[0];

        $objects = array_map(
            static function (IndirectObject $object) use ($attachmentObjectId, $embeddedFileObjectId): IndirectObject {
                if ($object->objectId !== $attachmentObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace(
                    '/\s*\/F\s+' . $embeddedFileObjectId . '\s+0\s+R/',
                    '',
                    $object->contents,
                    1,
                );
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'PDF/A attachment object 1 must serialize an /EF dictionary that references embedded file stream %d via /F.',
            $embeddedFileObjectId,
        ));

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA3bAttachmentObjectsWithoutEfUfReferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3b())
            ->title('Archive Package')
            ->attachment(
                'data.xml',
                '<root/>',
                'Source data',
                'application/xml',
                AssociatedFileRelationship::SOURCE,
            )
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $attachmentObjectId = $state->attachmentObjectIds[0];
        $embeddedFileObjectId = $state->embeddedFileObjectIds[0];

        $objects = array_map(
            static function (IndirectObject $object) use ($attachmentObjectId, $embeddedFileObjectId): IndirectObject {
                if ($object->objectId !== $attachmentObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace(
                    '/\s*\/UF\s+' . $embeddedFileObjectId . '\s+0\s+R/',
                    '',
                    $object->contents,
                    1,
                );
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'PDF/A attachment object 1 must serialize an /EF dictionary that references embedded file stream %d via /UF.',
            $embeddedFileObjectId,
        ));

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItAcceptsPdfA4fAttachmentObjectsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4f())
            ->title('Archive Package')
            ->attachment(
                'data.xml',
                '<root/>',
                'Source data',
                'application/xml',
            )
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);

        self::assertNotEmpty($state->attachmentObjectIds);

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4CatalogsWithOutputIntentArrays(): void
    {
        [$document, $state, $objects] = $this->pdfA4ObjectGraph(Profile::pdfA4());

        $objects = array_map(
            static function (IndirectObject $object): IndirectObject {
                if ($object->objectId !== 1) {
                    return $object;
                }

                return IndirectObject::plain(
                    $object->objectId,
                    str_replace(
                        '>>',
                        ' /OutputIntents [<< /Type /OutputIntent /S /GTS_PDFA1 /DestOutputProfile 99 0 R >>] >>',
                        $object->contents,
                    ),
                );
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4 must not serialize OutputIntents in the final PDF/A-4 object graph.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4MetadataWithoutRevisionMarkers(): void
    {
        [$document, $state, $objects] = $this->pdfA4ObjectGraph(Profile::pdfA4());

        $objects = array_map(
            static function (IndirectObject $object) use ($state): IndirectObject {
                if ($object->objectId !== $state->metadataObjectId) {
                    return $object;
                }

                $tamperedContents = str_replace('<pdfaid:rev>2020</pdfaid:rev>', '', $object->streamContents ?? $object->contents);

                return IndirectObject::stream(
                    $object->objectId,
                    $object->streamDictionaryContents ?? '<< /Type /Metadata /Subtype /XML /Length 0 >>',
                    $tamperedContents,
                );
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4 metadata stream must serialize <pdfaid:rev>2020</pdfaid:rev>.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsBasePdfA4MetadataWithConformanceMarkers(): void
    {
        [$document, $state, $objects] = $this->pdfA4ObjectGraph(Profile::pdfA4());

        $objects = array_map(
            static function (IndirectObject $object) use ($state): IndirectObject {
                if ($object->objectId !== $state->metadataObjectId) {
                    return $object;
                }

                $tamperedContents = str_replace(
                    '</rdf:Description>',
                    '    <pdfaid:conformance>F</pdfaid:conformance>' . "\n" . '  </rdf:Description>',
                    $object->streamContents ?? $object->contents,
                );

                return IndirectObject::stream(
                    $object->objectId,
                    $object->streamDictionaryContents ?? '<< /Type /Metadata /Subtype /XML /Length 0 >>',
                    $tamperedContents,
                );
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4 metadata stream must not serialize a pdfaid:conformance marker.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4CatalogsWithOptionalContentProperties(): void
    {
        [$document, $state, $objects] = $this->pdfA4ObjectGraph(Profile::pdfA4());

        $objects = array_map(
            static function (IndirectObject $object): IndirectObject {
                if ($object->objectId !== 1) {
                    return $object;
                }

                return IndirectObject::plain(
                    $object->objectId,
                    str_replace(
                        '>>',
                        ' /OCProperties << /OCGs [] /D << /Name (Engineering View) >> >> >>',
                        $object->contents,
                    ),
                );
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4 must not serialize /OCProperties in the current PDF/A-4 object graph.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4fRichMediaObjects(): void
    {
        [$document, $state, $objects] = $this->pdfA4ObjectGraph(Profile::pdfA4f());

        $objects[] = IndirectObject::plain(99, '<< /Type /Annot /Subtype /RichMedia /Rect [0 0 10 10] >>');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4f must not serialize RichMedia annotations or assets in the current PDF/A-4 object graph.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eOptionalContentGroupsWithoutOcProperties(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering View',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "/OC /Layer1 BDC\nEMC",
                    optionalContentGroups: [
                        'Layer1' => new OptionalContentGroup('Engineering View'),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $metadataObjects = new DocumentMetadataObjectBuilder()->buildObjects(
            $document,
            $state,
            new DateTimeImmutable('2026-04-12T10:00:00+02:00'),
            '',
        );
        $ocgObjectId = array_values($state->optionalContentGroupObjectIds)[0];
        $objects = [
            IndirectObject::plain(1, '<< /Type /Catalog /Pages 2 0 R /Metadata ' . $state->metadataObjectId . ' 0 R >>'),
            IndirectObject::plain(2, '<< /Type /Pages /Count 1 /Kids [3 0 R] >>'),
            IndirectObject::plain(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << /Properties << /Layer1 ' . $ocgObjectId . ' 0 R >> >> /Contents 4 0 R >>'),
            IndirectObject::stream(4, '<< /Length 0 >>', ''),
            IndirectObject::plain($ocgObjectId, '<< /Type /OCG /Name (Engineering View) >>'),
            ...$metadataObjects,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e must serialize /OCProperties when optional content groups are used.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eOptionalContentConfigurationsWithoutConfigsArray(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering Layers',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "/OC /Layer1 BDC\nq\n0 0 20 20 re\nf\nQ\nEMC",
                    optionalContentGroups: [
                        'Layer1' => new OptionalContentGroup('Base Geometry'),
                    ],
                ),
            ],
            optionalContentConfigurations: [
                new OptionalContentConfiguration('Exploded View', ['Layer1']),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);

        $objects = array_map(
            static function (IndirectObject $object): IndirectObject {
                if ($object->objectId !== 1) {
                    return $object;
                }

                return IndirectObject::plain($object->objectId, str_replace('/Configs [', '/BrokenConfigs [', $object->contents));
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e must serialize /Configs when optional content configurations are used.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eRichMediaAnnotationsWithoutRichMediaContentReference(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering Media',
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [
                        new RichMediaAnnotation(
                            40,
                            500,
                            160,
                            90,
                            'demo.mp4',
                            new EmbeddedFile('demo-video', 'video/mp4'),
                            RichMediaAssetType::VIDEO,
                            'Demo video',
                        ),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace('/\s*\/RichMediaContent\s+\d+\s+0\s+R/', '', $object->contents, 1);
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e requires RichMedia annotation 1 on page 1 to serialize /RichMediaContent and /RichMediaSettings.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eThreeDAnnotationsWithoutThreeDStreamReference(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering 3D',
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [
                        new ThreeDAnnotation(
                            40,
                            500,
                            160,
                            90,
                            'u3d-data',
                            ThreeDAssetType::U3D,
                            '3D model',
                        ),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace('/\s*\/3DD\s+\d+\s+0\s+R/', '', $object->contents, 1);
                self::assertNotNull($tamperedContents);

                return IndirectObject::plain($object->objectId, $tamperedContents);
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e requires 3D annotation 1 on page 1 to serialize /3DD and a poster appearance stream.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eThreeDAnnotationsWithUnsupportedEngineeringKeys(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering 3D',
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [
                        new ThreeDAnnotation(40, 500, 160, 90, 'u3d-data', ThreeDAssetType::U3D, '3D model'),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                return IndirectObject::plain($object->objectId, $object->contents . ' /Measure << /Type /Measure >>');
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e only allows the current constrained 3D annotation subset on page 1; key /Measure remains blocked.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eThreeDAnnotationsWithUnexpectedViewPreset(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering 3D',
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [
                        new ThreeDAnnotation(40, 500, 160, 90, 'u3d-data', ThreeDAssetType::U3D, '3D model', null, ThreeDViewPreset::EXPLODED),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                return IndirectObject::plain($object->objectId, str_replace('/3DV /Exploded', '/3DV /Broken', $object->contents));
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e requires 3D annotation 1 on page 1 to serialize the constrained /3DV /Exploded view preset.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eRichMediaAnnotationsWithUnsupportedRichMediaWiring(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering Media',
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [
                        new RichMediaAnnotation(
                            40,
                            500,
                            160,
                            90,
                            'demo.mp4',
                            new EmbeddedFile('demo-video', 'video/mp4'),
                            RichMediaAssetType::VIDEO,
                            'Demo video',
                        ),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                return IndirectObject::plain($object->objectId, $object->contents . ' /RichMediaInstance <<>>');
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e only allows the current constrained RichMedia annotation subset on page 1; additional /RichMediaInstance wiring remains blocked.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eRichMediaAnnotationsWithUnexpectedPresentationStyle(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering Media',
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [
                        new RichMediaAnnotation(
                            40,
                            500,
                            160,
                            90,
                            'demo.mp4',
                            new EmbeddedFile('demo-video', 'video/mp4'),
                            RichMediaAssetType::VIDEO,
                            'Demo video',
                            null,
                            RichMediaPresentationStyle::WINDOWED,
                        ),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $settingsObjectId = $state->pageAnnotationRelatedObjectIds[0][0][3];

        $objects = array_map(
            static function (IndirectObject $object) use ($settingsObjectId): IndirectObject {
                if ($object->objectId !== $settingsObjectId) {
                    return $object;
                }

                return IndirectObject::plain($object->objectId, str_replace('/Style /Windowed', '/Style /Fullscreen', $object->contents));
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e requires RichMedia annotation 1 on page 1 to serialize the constrained /Activation /Presentation style /Windowed and /Deactivation /Condition /XD.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eOptionalContentMembershipObjectsWithoutOcgReferences(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering Memberships',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "/OC /Assembly BDC\nq\n0 0 20 20 re\nf\nQ\nEMC",
                    optionalContentGroups: [
                        'LayerA' => new OptionalContentGroup('Base Geometry'),
                    ],
                    optionalContentMemberships: [
                        'Assembly' => new OptionalContentMembership('Assembly View', ['LayerA']),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $membershipObjectId = array_values($state->pageOptionalContentMembershipObjectIds[0])[0];

        $objects = array_map(
            static function (IndirectObject $object) use ($membershipObjectId): IndirectObject {
                if ($object->objectId !== $membershipObjectId) {
                    return $object;
                }

                return IndirectObject::plain(
                    $object->objectId,
                    '<< /Type /OCMD /Name (Assembly View) /P /AnyOn >>',
                );
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e must serialize optional content membership object ' . $membershipObjectId . ' as an /OCMD dictionary with /OCGs.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eOptionalContentMembershipObjectsWithoutConfiguredVeExpression(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering Membership Expressions',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "/OC /Exploded BDC\nq\n0 0 20 20 re\nf\nQ\nEMC",
                    optionalContentGroups: [
                        'LayerA' => new OptionalContentGroup('Base Geometry'),
                        'LayerB' => new OptionalContentGroup('Dimensions'),
                    ],
                    optionalContentMemberships: [
                        'Exploded' => new OptionalContentMembership(
                            'Exploded View',
                            ['LayerA', 'LayerB'],
                            visibilityExpression: OptionalContentVisibilityExpression::and(
                                OptionalContentVisibilityExpression::alias('LayerA'),
                                OptionalContentVisibilityExpression::not(
                                    OptionalContentVisibilityExpression::alias('LayerB'),
                                ),
                            ),
                        ),
                    ],
                ),
            ],
        );
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $membershipObjectId = array_values($state->pageOptionalContentMembershipObjectIds[0])[0];

        $objects = array_map(
            static function (IndirectObject $object) use ($membershipObjectId): IndirectObject {
                if ($object->objectId !== $membershipObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace('/\/VE\s+\[[^\]]+\]\s*/', '/P /AllOn ', $object->contents, 1);

                self::assertNotNull($tamperedContents);

                return IndirectObject::plain(
                    $object->objectId,
                    $tamperedContents,
                );
            },
            $objects,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e must serialize optional content membership object ' . $membershipObjectId . ' with the configured /VE expression.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4eRichMediaObjects(): void
    {
        [$document, $state, $objects] = $this->pdfA4ObjectGraph(Profile::pdfA4e());

        $objects[] = IndirectObject::plain(99, '<< /Type /Annot /Subtype /RichMedia /Rect [0 0 10 10] >>');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4e must not serialize RichMedia annotations or assets in the current PDF/A-4 object graph.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA4ThreeDObjects(): void
    {
        [$document, $state, $objects] = $this->pdfA4ObjectGraph(Profile::pdfA4());

        $objects[] = IndirectObject::plain(99, '<< /Type /Annot /Subtype /3D /Rect [0 0 10 10] >>');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-4 must not serialize 3D annotations in the current PDF/A-4 object graph.');

        new PdfAObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    private function pdfA2uDocument(): Document
    {
        return DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('PDF/A-2u Minimal Regression')
            ->author('kalle/pdf2')
            ->subject('Minimal PDF/A-2u regression fixture')
            ->language('de-DE')
            ->creator('Regression Fixture')
            ->creatorTool('PdfAObjectGraphValidatorTest')
            ->text('PDF/A-2u Regression Ж', TextOptions::make(
                x: 72,
                y: 760,
                fontSize: 18,
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();
    }

    private function allocateState(Document $document): DocumentSerializationPlanBuildState
    {
        $allocator = new DocumentSerializationPlanObjectIdAllocator();
        $taggedPdfObjectBuilder = new DocumentTaggedPdfObjectBuilder();

        return $allocator->allocate(
            $document,
            fn (int $nextStructParentId): array => $taggedPdfObjectBuilder->collectTaggedLinkStructure($document, $nextStructParentId),
            fn (int $nextStructParentId): array => $taggedPdfObjectBuilder->collectTaggedPageAnnotationStructure($document, $nextStructParentId),
            fn (array $fieldObjectIds, array $fieldRelatedObjectIds, int $nextStructParentId): array => $taggedPdfObjectBuilder->collectTaggedFormStructure(
                $document,
                array_values($fieldObjectIds),
                $fieldRelatedObjectIds,
                $nextStructParentId,
            ),
            static fn (): array => [],
        );
    }

    /**
     * @return array{Document, DocumentSerializationPlanBuildState, list<IndirectObject>}
     */
    private function pdfA4ObjectGraph(Profile $profile): array
    {
        $document = new Document(
            profile: $profile,
            title: 'Archive Copy',
            pages: [new Page(PageSize::A4())],
        );
        $state = $this->allocateState($document);
        $metadataObjects = new DocumentMetadataObjectBuilder()->buildObjects(
            $document,
            $state,
            new DateTimeImmutable('2026-04-12T10:00:00+02:00'),
            '',
        );

        $objects = [
            IndirectObject::plain(1, '<< /Type /Catalog /Pages 2 0 R /Metadata ' . $state->metadataObjectId . ' 0 R >>'),
            IndirectObject::plain(2, '<< /Type /Pages /Count 1 /Kids [3 0 R] >>'),
            IndirectObject::plain(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R >>'),
            IndirectObject::stream(4, '<< /Length 0 >>', ''),
            ...$metadataObjects,
        ];

        return [$document, $state, $objects];
    }
}
