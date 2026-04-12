<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_key_exists;

use InvalidArgumentException;

use Kalle\Pdf\Writer\IndirectObject;

use function preg_match;
use function preg_quote;
use function sprintf;
use function str_contains;

final class PdfA1ObjectGraphValidator
{
    public function __construct(
        private readonly PdfAAnnotationAppearancePolicy $pdfAAnnotationAppearancePolicy = new PdfAAnnotationAppearancePolicy(),
    ) {
    }

    /**
     * @param list<IndirectObject> $objects
     */
    public function assertValid(Document $document, DocumentSerializationPlanBuildState $state, array $objects): void
    {
        if (!$document->profile->isPdfA1()) {
            return;
        }

        $objectsById = $this->objectsById($objects);
        $catalogObject = $this->assertObjectExists($objectsById, 1, 'catalog');
        $pageTreeObject = $this->assertObjectExists($objectsById, 2, 'page tree');

        $this->assertCatalogObject($document, $state, $catalogObject);
        $this->assertPageTreeObject($state, $pageTreeObject);

        if ($state->metadataObjectId !== null) {
            $metadataObject = $this->assertObjectExists($objectsById, $state->metadataObjectId, 'metadata stream');

            if ($metadataObject->streamDictionaryContents === null || !str_contains($metadataObject->contents, '/Subtype /XML')) {
                throw new InvalidArgumentException('PDF/A-1 metadata stream must be serialized as an XML metadata stream object.');
            }
        }

        if ($state->iccProfileObjectId !== null) {
            $iccProfileObject = $this->assertObjectExists($objectsById, $state->iccProfileObjectId, 'ICC profile stream');

            if ($iccProfileObject->streamDictionaryContents === null) {
                throw new InvalidArgumentException('PDF/A-1 ICC profile must be serialized as a stream object.');
            }
        }

        if ($state->infoObjectId !== null) {
            $this->assertObjectExists($objectsById, $state->infoObjectId, 'info dictionary');
        }

        if ($state->acroFormObjectId !== null) {
            $acroFormObject = $this->assertObjectExists($objectsById, $state->acroFormObjectId, 'AcroForm');

            if (str_contains($acroFormObject->contents, '/Helv')) {
                throw new InvalidArgumentException('Profile PDF/A-1 must not serialize AcroForm default resources with the built-in /Helv fallback.');
            }
        }

        if ($state->structTreeRootObjectId !== null) {
            $structTreeRootObject = $this->assertObjectExists($objectsById, $state->structTreeRootObjectId, 'StructTreeRoot');
            $this->assertReferencePresent(
                $structTreeRootObject->contents,
                $state->documentStructElemObjectId,
                'Profile PDF/A-1 tagged structure must serialize the document structure element as a StructTreeRoot kid.',
            );

            if ($state->parentTreeObjectId !== null) {
                $this->assertReferencePresent(
                    $structTreeRootObject->contents,
                    $state->parentTreeObjectId,
                    'Profile PDF/A-1 tagged structure must serialize the ParentTree reference in StructTreeRoot.',
                );
                $this->assertObjectExists($objectsById, $state->parentTreeObjectId, 'ParentTree');
            }
        }

        foreach ($state->pageObjectIds as $pageIndex => $pageObjectId) {
            $pageObject = $this->assertObjectExists($objectsById, $pageObjectId, sprintf('page object %d', $pageIndex + 1));
            $this->assertPageObject(
                $document,
                $state,
                $pageObject,
                $pageIndex,
                $pageObjectId,
            );
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                $annotationObjectId = $state->pageAnnotationObjectIds[$pageIndex][$annotationIndex] ?? null;

                if ($annotationObjectId === null) {
                    throw new InvalidArgumentException(sprintf(
                        'Missing serialized page annotation object allocation for annotation %d on page %d.',
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }

                $annotationObject = $this->assertObjectExists(
                    $objectsById,
                    $annotationObjectId,
                    sprintf('page annotation %d on page %d', $annotationIndex + 1, $pageIndex + 1),
                );

                if (str_contains($annotationObject->contents, '/Popup ')) {
                    throw new InvalidArgumentException(sprintf(
                        'Profile %s does not allow popup related objects for page annotation %d on page %d.',
                        $document->profile->name(),
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }

                $appearanceObjectId = $state->pageAnnotationAppearanceObjectIds[$pageIndex][$annotationIndex] ?? null;

                if ($this->pdfAAnnotationAppearancePolicy->requiresAppearanceStream($document, $annotation)) {
                    if ($appearanceObjectId === null) {
                        throw new InvalidArgumentException(sprintf(
                            'Profile %s requires a serialized annotation appearance stream object for page annotation %d on page %d.',
                            $document->profile->name(),
                            $annotationIndex + 1,
                            $pageIndex + 1,
                        ));
                    }

                    if (!$this->containsAppearanceReference($annotationObject->contents, $appearanceObjectId)) {
                        throw new InvalidArgumentException(sprintf(
                            'Profile %s requires page annotation %d on page %d to serialize /AP << /N %d 0 R >>.',
                            $document->profile->name(),
                            $annotationIndex + 1,
                            $pageIndex + 1,
                            $appearanceObjectId,
                        ));
                    }

                    $appearanceObject = $this->assertObjectExists(
                        $objectsById,
                        $appearanceObjectId,
                        sprintf('annotation appearance stream for annotation %d on page %d', $annotationIndex + 1, $pageIndex + 1),
                    );

                    if (
                        $appearanceObject->streamDictionaryContents === null
                        || !str_contains($appearanceObject->streamDictionaryContents, '/Type /XObject')
                        || !str_contains($appearanceObject->streamDictionaryContents, '/Subtype /Form')
                    ) {
                        throw new InvalidArgumentException(sprintf(
                            'Profile %s requires annotation appearance stream %d for page annotation %d on page %d to serialize as a form XObject stream.',
                            $document->profile->name(),
                            $appearanceObjectId,
                            $annotationIndex + 1,
                            $pageIndex + 1,
                        ));
                    }
                }

                foreach ($state->pageAnnotationRelatedObjectIds[$pageIndex][$annotationIndex] ?? [] as $relatedObjectId) {
                    $relatedObject = $this->assertObjectExists(
                        $objectsById,
                        $relatedObjectId,
                        sprintf('related object %d for page annotation %d on page %d', $relatedObjectId, $annotationIndex + 1, $pageIndex + 1),
                    );

                    if (str_contains($relatedObject->contents, '/Subtype /Popup')) {
                        throw new InvalidArgumentException(sprintf(
                            'Profile %s does not allow popup related objects for page annotation %d on page %d.',
                            $document->profile->name(),
                            $annotationIndex + 1,
                            $pageIndex + 1,
                        ));
                    }
                }
            }
        }
    }

    /**
     * @param list<IndirectObject> $objects
     * @return array<int, IndirectObject>
     */
    private function objectsById(array $objects): array
    {
        $objectsById = [];

        foreach ($objects as $object) {
            $objectsById[$object->objectId] = $object;
        }

        return $objectsById;
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertObjectExists(array $objectsById, int $objectId, string $label): IndirectObject
    {
        if (!isset($objectsById[$objectId])) {
            throw new InvalidArgumentException(sprintf(
                'Missing serialized PDF object %d for %s in the final PDF/A-1 object graph.',
                $objectId,
                $label,
            ));
        }

        return $objectsById[$objectId];
    }

    private function assertCatalogObject(Document $document, DocumentSerializationPlanBuildState $state, IndirectObject $catalogObject): void
    {
        $this->assertReferencePresent(
            $catalogObject->contents,
            2,
            'PDF/A-1 catalog must reference the page tree.',
        );

        if ($state->metadataObjectId !== null) {
            $this->assertReferencePresent(
                $catalogObject->contents,
                $state->metadataObjectId,
                'PDF/A-1 catalog must reference the metadata stream.',
            );
        }

        if ($state->iccProfileObjectId !== null) {
            $this->assertReferencePresent(
                $catalogObject->contents,
                $state->iccProfileObjectId,
                'PDF/A-1 catalog must reference the ICC output intent profile.',
            );

            if (!str_contains($catalogObject->contents, '/OutputIntents [')) {
                throw new InvalidArgumentException('PDF/A-1 catalog must serialize an OutputIntents array.');
            }
        }

        if ($document->language !== null && !str_contains($catalogObject->contents, '/Lang ')) {
            throw new InvalidArgumentException('PDF/A-1 catalog must serialize the document language.');
        }

        if ($state->acroFormObjectId !== null) {
            $this->assertReferencePresent(
                $catalogObject->contents,
                $state->acroFormObjectId,
                'PDF/A-1 catalog must reference the AcroForm object.',
            );
        }

        if ($document->profile->requiresTaggedPdf()) {
            if (!str_contains($catalogObject->contents, '/MarkInfo << /Marked true >>')) {
                throw new InvalidArgumentException('PDF/A-1 tagged catalog must serialize /MarkInfo << /Marked true >>.');
            }

            if ($state->structTreeRootObjectId === null) {
                throw new InvalidArgumentException('PDF/A-1 tagged catalog requires a StructTreeRoot object ID.');
            }

            $this->assertReferencePresent(
                $catalogObject->contents,
                $state->structTreeRootObjectId,
                'PDF/A-1 tagged catalog must reference the StructTreeRoot object.',
            );
        }
    }

    private function assertPageTreeObject(DocumentSerializationPlanBuildState $state, IndirectObject $pageTreeObject): void
    {
        foreach ($state->pageObjectIds as $pageObjectId) {
            $this->assertReferencePresent(
                $pageTreeObject->contents,
                $pageObjectId,
                sprintf('PDF/A-1 page tree must reference page object %d.', $pageObjectId),
            );
        }
    }

    private function assertPageObject(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        IndirectObject $pageObject,
        int $pageIndex,
        int $pageObjectId,
    ): void {
        $this->assertReferencePresent(
            $pageObject->contents,
            2,
            sprintf('PDF/A-1 page object %d must reference the page tree parent.', $pageIndex + 1),
        );
        $this->assertReferencePresent(
            $pageObject->contents,
            $state->contentObjectIds[$pageIndex],
            sprintf('PDF/A-1 page object %d must reference its content stream.', $pageIndex + 1),
        );

        foreach ([...($state->pageAnnotationObjectIds[$pageIndex] ?? []), ...($state->pageFormWidgetObjectIds[$pageIndex] ?? [])] as $annotationObjectId) {
            $this->assertReferencePresent(
                $pageObject->contents,
                $annotationObjectId,
                sprintf('PDF/A-1 page object %d must reference annotation/widget object %d.', $pageIndex + 1, $annotationObjectId),
            );
        }

        if (
            $document->profile->requiresTaggedPdf()
            && array_key_exists($pageIndex, $state->pageStructParentIds)
            && !str_contains($pageObject->contents, '/StructParents ')
        ) {
            throw new InvalidArgumentException(sprintf(
                'PDF/A-1 tagged page object %d must serialize /StructParents.',
                $pageIndex + 1,
            ));
        }

        if ($document->profile->requiresPageAnnotationTabOrder() && ($state->pageAnnotationObjectIds[$pageIndex] ?? []) !== [] && !str_contains($pageObject->contents, '/Tabs /S')) {
            throw new InvalidArgumentException(sprintf(
                'PDF/A-1 page object %d must serialize /Tabs /S when annotations are present.',
                $pageIndex + 1,
            ));
        }
    }

    private function assertReferencePresent(string $contents, ?int $objectId, string $message): void
    {
        if ($objectId === null) {
            throw new InvalidArgumentException($message);
        }

        if (preg_match('/\b' . preg_quote((string) $objectId, '/') . '\s+0\s+R\b/', $contents) !== 1) {
            throw new InvalidArgumentException($message);
        }
    }

    private function containsAppearanceReference(string $contents, int $appearanceObjectId): bool
    {
        return preg_match(
            '/\/AP\s*<<\s*\/N\s+' . preg_quote((string) $appearanceObjectId, '/') . '\s+0\s+R\s*>>/',
            $contents,
        ) === 1;
    }
}
