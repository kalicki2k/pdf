<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function array_map;
use function array_values;
use function dirname;

use InvalidArgumentException;

use function iterator_to_array;

use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuildState;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentTaggedPdfObjectBuilder;
use Kalle\Pdf\Document\PdfAObjectGraphValidator;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\IndirectObject;
use PHPUnit\Framework\TestCase;

use function preg_replace;

final class PdfAObjectGraphValidatorTest extends TestCase
{
    public function testItRejectsPdfA2uCatalogsWithoutMetadataReferences(): void
    {
        $document = $this->pdfA2uDocument();
        $state = $this->allocateState($document);
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);

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

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA2uCatalogsWithoutOutputIntentArrays(): void
    {
        $document = $this->pdfA2uDocument();
        $state = $this->allocateState($document);
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);

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

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
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
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);

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

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
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
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);
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

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
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
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);
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
            'Profile PDF/A-2u requires external link annotation 1 on page 1 to serialize a URI action in the final PDF/A-2/3 object graph.',
        );

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
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
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);
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
            'Profile PDF/A-2u requires page annotation 1 on page 1 to serialize /Subtype /Text in the final PDF/A-2/3 object graph.',
        );

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
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
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);
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

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
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
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);
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

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
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
        $objects = iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects);
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

        (new PdfAObjectGraphValidator())->assertValid($document, $state, $objects);
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
            ->text('PDF/A-2u Regression Ж', new TextOptions(
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
}
