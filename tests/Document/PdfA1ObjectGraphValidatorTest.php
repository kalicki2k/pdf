<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function array_map;
use function array_values;
use function iterator_to_array;
use function preg_replace;

use InvalidArgumentException;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuildState;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentTaggedPdfObjectBuilder;
use Kalle\Pdf\Document\PdfA1ObjectGraphValidator;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\IndirectObject;
use PHPUnit\Framework\TestCase;

final class PdfA1ObjectGraphValidatorTest extends TestCase
{
    public function testItRejectsMissingSerializedAnnotationAppearanceReferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
                tag: TaggedStructureTag::P,
            ))
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA')
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
        $this->expectExceptionMessage(
            'Profile PDF/A-1a requires page annotation 1 on page 1 to serialize /AP << /N '
            . $appearanceObjectId
            . ' 0 R >>.',
        );

        new PdfA1ObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsMissingCatalogMetadataReferences(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            keywords: 'archive, pdfa',
        );
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
        $this->expectExceptionMessage('PDF/A-1 catalog must reference the metadata stream.');

        new PdfA1ObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsMissingTaggedCatalogStructureReferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
                tag: TaggedStructureTag::P,
            ))
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);

        self::assertNotNull($state->structTreeRootObjectId);

        $objects = array_map(
            static function (IndirectObject $object) use ($state): IndirectObject {
                if ($object->objectId !== 1) {
                    return $object;
                }

                $tamperedContents = preg_replace(
                    '/\s*\/StructTreeRoot\s+' . $state->structTreeRootObjectId . '\s+0\s+R/',
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
        $this->expectExceptionMessage('PDF/A-1 tagged catalog must reference the StructTreeRoot object.');

        new PdfA1ObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsMissingAcroFormFieldReferencesInTheFinalObjectGraph(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Form')
            ->language('de-DE')
            ->textField('customer_name', 40, 500, 160, 18, 'Ada', 'Customer name')
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);

        self::assertNotNull($state->acroFormObjectId);

        $objects = array_map(
            static function (IndirectObject $object) use ($state): IndirectObject {
                if ($object->objectId !== $state->acroFormObjectId) {
                    return $object;
                }

                $fieldObjectId = $state->acroFormFieldObjectIds[0];
                $tamperedContents = preg_replace(
                    '/\s*' . $fieldObjectId . '\s+0\s+R/',
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
            'PDF/A-1 AcroForm must reference field object %d.',
            $state->acroFormFieldObjectIds[0],
        ));

        new PdfA1ObjectGraphValidator()->assertValid($document, $state, $objects);
    }

    public function testItRejectsAnnotationObjectsWithoutParentPageReferences(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
                tag: TaggedStructureTag::P,
            ))
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA')
            ->build();
        $state = $this->allocateState($document);
        $objects = iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects);
        $annotationObjectId = $state->pageAnnotationObjectIds[0][0];
        $pageObjectId = $state->pageObjectIds[0];

        $objects = array_map(
            static function (IndirectObject $object) use ($annotationObjectId, $pageObjectId): IndirectObject {
                if ($object->objectId !== $annotationObjectId) {
                    return $object;
                }

                $tamperedContents = preg_replace(
                    '/\s*\/P\s+' . $pageObjectId . '\s+0\s+R/',
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
        $this->expectExceptionMessage('PDF/A-1 page annotation 1 on page 1 must reference its parent page object.');

        new PdfA1ObjectGraphValidator()->assertValid($document, $state, $objects);
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
