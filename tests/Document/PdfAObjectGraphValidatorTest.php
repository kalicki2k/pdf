<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function array_map;
use function array_values;
use function dirname;
use function iterator_to_array;

use InvalidArgumentException;
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
