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

final class PdfAObjectGraphValidator
{
    public function __construct(
        private readonly DocumentAttachmentRelationshipResolver $attachmentRelationshipResolver = new DocumentAttachmentRelationshipResolver(),
    ) {
    }

    /**
     * @param list<IndirectObject> $objects
     */
    public function assertValid(Document $document, DocumentSerializationPlanBuildState $state, array $objects): void
    {
        if (!$document->profile->isPdfA()) {
            return;
        }

        $objectsById = $this->objectsById($objects);
        $catalogObject = $this->assertObjectExists($objectsById, 1, 'catalog');
        $pageTreeObject = $this->assertObjectExists($objectsById, 2, 'page tree');

        $this->assertCatalogObject($document, $state, $catalogObject);
        $this->assertPageTreeObject($state, $pageTreeObject);
        $this->assertMetadataObjects($state, $objectsById);
        $this->assertAcroFormObjects($state, $catalogObject, $objectsById);
        $this->assertTaggedObjects($document, $state, $catalogObject, $objectsById);
        $this->assertAttachmentReferences($document, $state, $catalogObject);

        foreach ($state->pageObjectIds as $pageIndex => $pageObjectId) {
            $pageObject = $this->assertObjectExists($objectsById, $pageObjectId, sprintf('page object %d', $pageIndex + 1));
            $this->assertPageObject($document, $state, $pageObject, $pageIndex);
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
                'Missing serialized PDF object %d for %s in the final %s object graph.',
                $objectId,
                $label,
                $this->profileLabel(),
            ));
        }

        return $objectsById[$objectId];
    }

    private function assertCatalogObject(Document $document, DocumentSerializationPlanBuildState $state, IndirectObject $catalogObject): void
    {
        $this->assertReferencePresent(
            $catalogObject->contents,
            2,
            'PDF/A catalog must reference the page tree.',
        );

        if ($state->metadataObjectId !== null) {
            $this->assertReferencePresent(
                $catalogObject->contents,
                $state->metadataObjectId,
                'PDF/A catalog must reference the metadata stream.',
            );
        }

        if ($state->iccProfileObjectId !== null) {
            if (!str_contains($catalogObject->contents, '/OutputIntents [')) {
                throw new InvalidArgumentException('PDF/A catalog must serialize an OutputIntents array.');
            }

            $this->assertReferencePresent(
                $catalogObject->contents,
                $state->iccProfileObjectId,
                'PDF/A catalog must reference the ICC output intent profile.',
            );
        }

        if ($document->language !== null && !str_contains($catalogObject->contents, '/Lang ')) {
            throw new InvalidArgumentException('PDF/A catalog must serialize the document language.');
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertMetadataObjects(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        if ($state->metadataObjectId !== null) {
            $metadataObject = $this->assertObjectExists($objectsById, $state->metadataObjectId, 'metadata stream');

            if ($metadataObject->streamDictionaryContents === null || !str_contains($metadataObject->contents, '/Subtype /XML')) {
                throw new InvalidArgumentException('PDF/A metadata stream must be serialized as an XML metadata stream object.');
            }
        }

        if ($state->iccProfileObjectId !== null) {
            $iccProfileObject = $this->assertObjectExists($objectsById, $state->iccProfileObjectId, 'ICC profile stream');

            if ($iccProfileObject->streamDictionaryContents === null) {
                throw new InvalidArgumentException('PDF/A ICC profile must be serialized as a stream object.');
            }
        }

        if ($state->infoObjectId !== null) {
            $this->assertObjectExists($objectsById, $state->infoObjectId, 'info dictionary');
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertAcroFormObjects(
        DocumentSerializationPlanBuildState $state,
        IndirectObject $catalogObject,
        array $objectsById,
    ): void {
        if ($state->acroFormObjectId === null) {
            return;
        }

        $this->assertReferencePresent(
            $catalogObject->contents,
            $state->acroFormObjectId,
            'PDF/A catalog must reference the AcroForm object.',
        );

        $acroFormObject = $this->assertObjectExists($objectsById, $state->acroFormObjectId, 'AcroForm');

        foreach ($state->acroFormFieldObjectIds as $fieldObjectId) {
            $this->assertReferencePresent(
                $acroFormObject->contents,
                $fieldObjectId,
                sprintf('PDF/A AcroForm must reference field object %d.', $fieldObjectId),
            );
            $this->assertObjectExists($objectsById, $fieldObjectId, sprintf('AcroForm field %d', $fieldObjectId));
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertTaggedObjects(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        IndirectObject $catalogObject,
        array $objectsById,
    ): void {
        if (!$document->profile->requiresTaggedPdf()) {
            return;
        }

        if (!str_contains($catalogObject->contents, '/MarkInfo << /Marked true >>')) {
            throw new InvalidArgumentException('PDF/A tagged catalog must serialize /MarkInfo << /Marked true >>.');
        }

        $this->assertReferencePresent(
            $catalogObject->contents,
            $state->structTreeRootObjectId,
            'PDF/A tagged catalog must reference the StructTreeRoot object.',
        );

        if ($state->structTreeRootObjectId === null) {
            throw new InvalidArgumentException('PDF/A tagged catalog requires a StructTreeRoot object ID.');
        }

        $structTreeRootObject = $this->assertObjectExists($objectsById, $state->structTreeRootObjectId, 'StructTreeRoot');
        $this->assertReferencePresent(
            $structTreeRootObject->contents,
            $state->documentStructElemObjectId,
            'PDF/A tagged structure must serialize the document structure element as a StructTreeRoot kid.',
        );

        if ($state->parentTreeObjectId !== null) {
            $this->assertReferencePresent(
                $structTreeRootObject->contents,
                $state->parentTreeObjectId,
                'PDF/A tagged structure must serialize the ParentTree reference in StructTreeRoot.',
            );
            $this->assertObjectExists($objectsById, $state->parentTreeObjectId, 'ParentTree');
        }
    }

    private function assertAttachmentReferences(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        IndirectObject $catalogObject,
    ): void {
        if ($state->attachmentObjectIds === []) {
            return;
        }

        if (!str_contains($catalogObject->contents, '/Names ')) {
            throw new InvalidArgumentException('PDF/A catalog must serialize the embedded file name tree when attachments are present.');
        }

        foreach ($state->attachmentObjectIds as $attachmentObjectId) {
            $this->assertReferencePresent(
                $catalogObject->contents,
                $attachmentObjectId,
                sprintf('PDF/A catalog must reference attachment object %d.', $attachmentObjectId),
            );
        }

        $associatedAttachmentObjectIds = [];

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            if ($this->attachmentRelationshipResolver->resolve($document, $attachment) === null) {
                continue;
            }

            $associatedAttachmentObjectIds[] = $state->attachmentObjectIds[$attachmentIndex];
        }

        if ($associatedAttachmentObjectIds === []) {
            return;
        }

        if (!str_contains($catalogObject->contents, '/AF [')) {
            throw new InvalidArgumentException('PDF/A catalog must serialize an /AF array for associated files.');
        }

        foreach ($associatedAttachmentObjectIds as $attachmentObjectId) {
            $this->assertReferencePresent(
                $catalogObject->contents,
                $attachmentObjectId,
                sprintf('PDF/A catalog must reference associated file object %d in /AF.', $attachmentObjectId),
            );
        }
    }

    private function assertPageTreeObject(DocumentSerializationPlanBuildState $state, IndirectObject $pageTreeObject): void
    {
        foreach ($state->pageObjectIds as $pageObjectId) {
            $this->assertReferencePresent(
                $pageTreeObject->contents,
                $pageObjectId,
                sprintf('PDF/A page tree must reference page object %d.', $pageObjectId),
            );
        }
    }

    private function assertPageObject(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        IndirectObject $pageObject,
        int $pageIndex,
    ): void {
        $this->assertReferencePresent(
            $pageObject->contents,
            2,
            sprintf('PDF/A page object %d must reference the page tree parent.', $pageIndex + 1),
        );
        $this->assertReferencePresent(
            $pageObject->contents,
            $state->contentObjectIds[$pageIndex],
            sprintf('PDF/A page object %d must reference its content stream.', $pageIndex + 1),
        );

        foreach ([...($state->pageAnnotationObjectIds[$pageIndex] ?? []), ...($state->pageFormWidgetObjectIds[$pageIndex] ?? [])] as $annotationObjectId) {
            $this->assertReferencePresent(
                $pageObject->contents,
                $annotationObjectId,
                sprintf('PDF/A page object %d must reference annotation/widget object %d.', $pageIndex + 1, $annotationObjectId),
            );
        }

        if (
            $document->profile->requiresTaggedPdf()
            && array_key_exists($pageIndex, $state->pageStructParentIds)
            && !str_contains($pageObject->contents, '/StructParents ')
        ) {
            throw new InvalidArgumentException(sprintf(
                'PDF/A tagged page object %d must serialize /StructParents.',
                $pageIndex + 1,
            ));
        }

        if ($document->profile->requiresPageAnnotationTabOrder() && ($state->pageAnnotationObjectIds[$pageIndex] ?? []) !== [] && !str_contains($pageObject->contents, '/Tabs /S')) {
            throw new InvalidArgumentException(sprintf(
                'PDF/A page object %d must serialize /Tabs /S when annotations are present.',
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

    private function profileLabel(): string
    {
        return 'PDF/A';
    }
}
