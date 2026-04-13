<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_key_exists;

use InvalidArgumentException;

use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\TextAnnotation;
use Kalle\Pdf\Writer\IndirectObject;

use function preg_match;
use function preg_quote;
use function sprintf;
use function str_contains;
use function str_replace;

final class PdfAObjectGraphValidator
{
    public function __construct(
        private readonly DocumentAttachmentRelationshipResolver $attachmentRelationshipResolver = new DocumentAttachmentRelationshipResolver(),
        private readonly PdfAAnnotationAppearancePolicy $pdfAAnnotationAppearancePolicy = new PdfAAnnotationAppearancePolicy(),
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
        $this->assertAcroFormObjects($document, $state, $catalogObject, $objectsById);
        $this->assertTaggedObjects($document, $state, $catalogObject, $objectsById);
        $this->assertAttachmentReferences($document, $state, $catalogObject, $objectsById);

        foreach ($state->pageObjectIds as $pageIndex => $pageObjectId) {
            $pageObject = $this->assertObjectExists($objectsById, $pageObjectId, sprintf('page object %d', $pageIndex + 1));
            $this->assertPageObject($document, $state, $pageObject, $pageIndex);
        }

        $this->assertAnnotationObjects($document, $state, $objectsById);
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
        Document $document,
        DocumentSerializationPlanBuildState $state,
        IndirectObject $catalogObject,
        array $objectsById,
    ): void {
        if (($document->profile->isPdfA2() || $document->profile->isPdfA3()) && $state->acroFormObjectId !== null) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s must not serialize AcroForm objects in the final PDF/A-2/3 object graph.',
                $document->profile->name(),
            ));
        }

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

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertAttachmentReferences(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        IndirectObject $catalogObject,
        array $objectsById,
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

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            $attachmentObjectId = $state->attachmentObjectIds[$attachmentIndex];
            $embeddedFileObjectId = $state->embeddedFileObjectIds[$attachmentIndex];
            $attachmentObject = $this->assertObjectExists($objectsById, $attachmentObjectId, sprintf('attachment object %d', $attachmentIndex + 1));
            $embeddedFileObject = $this->assertObjectExists($objectsById, $embeddedFileObjectId, sprintf('embedded file stream %d', $attachmentIndex + 1));

            if (!str_contains($attachmentObject->contents, '/Type /Filespec')) {
                throw new InvalidArgumentException(sprintf(
                    'PDF/A attachment object %d must serialize as a /Filespec dictionary.',
                    $attachmentIndex + 1,
                ));
            }

            if (!str_contains($attachmentObject->contents, '/F ' . $this->pdfString($attachment->filename))) {
                throw new InvalidArgumentException(sprintf(
                    'PDF/A attachment object %d must serialize /F for filename "%s".',
                    $attachmentIndex + 1,
                    $attachment->filename,
                ));
            }

            if (!str_contains($attachmentObject->contents, '/UF ' . $this->pdfString($attachment->filename))) {
                throw new InvalidArgumentException(sprintf(
                    'PDF/A attachment object %d must serialize /UF for filename "%s".',
                    $attachmentIndex + 1,
                    $attachment->filename,
                ));
            }

            if (
                preg_match('/\/EF\s*<<[^>]*\/F\s+' . preg_quote((string) $embeddedFileObjectId, '/') . '\s+0\s+R[^>]*>>/', $attachmentObject->contents) !== 1
            ) {
                throw new InvalidArgumentException(sprintf(
                    'PDF/A attachment object %d must serialize an /EF dictionary that references embedded file stream %d via /F.',
                    $attachmentIndex + 1,
                    $embeddedFileObjectId,
                ));
            }

            if (
                preg_match('/\/EF\s*<<[^>]*\/UF\s+' . preg_quote((string) $embeddedFileObjectId, '/') . '\s+0\s+R[^>]*>>/', $attachmentObject->contents) !== 1
            ) {
                throw new InvalidArgumentException(sprintf(
                    'PDF/A attachment object %d must serialize an /EF dictionary that references embedded file stream %d via /UF.',
                    $attachmentIndex + 1,
                    $embeddedFileObjectId,
                ));
            }

            if ($embeddedFileObject->streamDictionaryContents === null || !str_contains($embeddedFileObject->streamDictionaryContents, '/Type /EmbeddedFile')) {
                throw new InvalidArgumentException(sprintf(
                    'PDF/A embedded file stream %d must serialize as an /EmbeddedFile stream object.',
                    $attachmentIndex + 1,
                ));
            }

            if ($attachment->embeddedFile->mimeType !== null && !str_contains($embeddedFileObject->streamDictionaryContents, '/Subtype /')) {
                throw new InvalidArgumentException(sprintf(
                    'PDF/A embedded file stream %d must serialize /Subtype for MIME-typed attachments.',
                    $attachmentIndex + 1,
                ));
            }

            $relationship = $this->attachmentRelationshipResolver->resolve($document, $attachment);

            if ($relationship !== null && !str_contains($attachmentObject->contents, '/AFRelationship /' . $relationship->value)) {
                throw new InvalidArgumentException(sprintf(
                    'PDF/A attachment object %d must serialize /AFRelationship /%s.',
                    $attachmentIndex + 1,
                    $relationship->value,
                ));
            }
        }

        $associatedAttachmentObjectIds = [];

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            if ($this->attachmentRelationshipResolver->resolve($document, $attachment) === null) {
                if ($document->profile->isPdfA2() || $document->profile->isPdfA3()) {
                    throw new InvalidArgumentException(sprintf(
                        'Profile %s must not serialize non-associated attachments in the final PDF/A-2/3 object graph (attachment %d).',
                        $document->profile->name(),
                        $attachmentIndex + 1,
                    ));
                }

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

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
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

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertAnnotationObjects(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        array $objectsById,
    ): void {
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

                $this->assertReferencePresent(
                    $annotationObject->contents,
                    $state->pageObjectIds[$pageIndex],
                    sprintf(
                        'PDF/A page annotation %d on page %d must reference its parent page object.',
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ),
                );

                $this->assertPdfA23AnnotationObject($document, $annotation, $annotationObject, $pageIndex, $annotationIndex);

                if (!$this->pdfAAnnotationAppearancePolicy->requiresAppearanceStream($document, $annotation)) {
                    continue;
                }

                $appearanceObjectId = $state->pageAnnotationAppearanceObjectIds[$pageIndex][$annotationIndex] ?? null;

                if ($appearanceObjectId === null) {
                    throw new InvalidArgumentException(sprintf(
                        'PDF/A requires a serialized annotation appearance stream object for page annotation %d on page %d.',
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }

                if (!$this->containsAppearanceReference($annotationObject->contents, $appearanceObjectId)) {
                    throw new InvalidArgumentException(sprintf(
                        'PDF/A requires page annotation %d on page %d to serialize /AP << /N %d 0 R >>.',
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
                        'PDF/A requires annotation appearance stream %d for page annotation %d on page %d to serialize as a form XObject stream.',
                        $appearanceObjectId,
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }
            }
        }
    }

    private function assertPdfA23AnnotationObject(
        Document $document,
        object $annotation,
        IndirectObject $annotationObject,
        int $pageIndex,
        int $annotationIndex,
    ): void {
        if (!$document->profile->isPdfA2() && !$document->profile->isPdfA3()) {
            return;
        }

        $expectedSubtype = match (true) {
            $annotation instanceof LinkAnnotation => 'Link',
            $annotation instanceof TextAnnotation => 'Text',
            $annotation instanceof HighlightAnnotation => 'Highlight',
            $annotation instanceof FreeTextAnnotation => 'FreeText',
            default => null,
        };

        if ($expectedSubtype === null) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s only supports the current explicit PDF/A-2/3 annotation scope in the final object graph for page annotation %d on page %d.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
            ));
        }

        if (!str_contains($annotationObject->contents, '/Subtype /' . $expectedSubtype)) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires page annotation %d on page %d to serialize /Subtype /%s in the final PDF/A-2/3 object graph.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
                $expectedSubtype,
            ));
        }

        if (!$annotation instanceof LinkAnnotation) {
            return;
        }

        if ($annotation->target->isExternalUrl()) {
            if (!str_contains($annotationObject->contents, '/A << /S /URI /URI ')) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s requires external link annotation %d on page %d to serialize a URI action in the final PDF/A-2/3 object graph.',
                    $document->profile->name(),
                    $annotationIndex + 1,
                    $pageIndex + 1,
                ));
            }

            return;
        }

        if (!str_contains($annotationObject->contents, '/Dest ')) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires internal link annotation %d on page %d to serialize a /Dest target in the final PDF/A-2/3 object graph.',
                $document->profile->name(),
                $annotationIndex + 1,
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

    private function profileLabel(): string
    {
        return 'PDF/A';
    }
}
