<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_key_exists;
use function array_map;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_contains;
use function str_replace;

use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\OptionalContentGroup;
use Kalle\Pdf\Page\OptionalContentVisibilityExpression;
use Kalle\Pdf\Page\RichMediaAnnotation;
use Kalle\Pdf\Page\TextAnnotation;
use Kalle\Pdf\Writer\IndirectObject;

final class PdfAObjectGraphValidator
{
    private ?Profile $activeProfile = null;

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

        $this->activeProfile = $document->profile;

        $objectsById = $this->objectsById($objects);
        $catalogObject = $this->assertObjectExists($objectsById, 1, 'catalog');
        $pageTreeObject = $this->assertObjectExists($objectsById, 2, 'page tree');

        $this->assertCatalogObject($document, $state, $catalogObject);
        $this->assertPageTreeObject($state, $pageTreeObject);
        $this->assertMetadataObjects($state, $objectsById);
        $this->assertAcroFormObjects($document, $state, $catalogObject, $objectsById);
        $this->assertTaggedObjects($document, $state, $catalogObject, $objectsById);
        $this->assertAttachmentReferences($document, $state, $catalogObject, $objectsById);
        $this->assertPdfA4EngineeringObjects($document, $state, $catalogObject, $objectsById);

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
            throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
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
                throw new DocumentValidationException(
                    DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID,
                    'PDF/A catalog must serialize an OutputIntents array.',
                );
            }

            $this->assertReferencePresent(
                $catalogObject->contents,
                $state->iccProfileObjectId,
                'PDF/A catalog must reference the ICC output intent profile.',
            );
        }

        if ($document->language !== null && !str_contains($catalogObject->contents, '/Lang ')) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID,
                'PDF/A catalog must serialize the document language.',
            );
        }

        if ($document->profile->isPdfA4() && str_contains($catalogObject->contents, '/OutputIntents [')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_METADATA_INVALID, sprintf(
                'Profile %s must not serialize OutputIntents in the final PDF/A-4 object graph.',
                $document->profile->name(),
            ));
        }

        if (
            $document->profile->pdfaConformance() === 'E'
            && $state->optionalContentGroupObjectIds !== []
            && !str_contains($catalogObject->contents, '/OCProperties <<')
        ) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                'Profile %s must serialize /OCProperties when optional content groups are used.',
                $document->profile->name(),
            ));
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
                throw new DocumentValidationException(
                    DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID,
                    'PDF/A metadata stream must be serialized as an XML metadata stream object.',
                );
            }

            $this->assertPdfA4MetadataObject($metadataObject);
        }

        if ($state->iccProfileObjectId !== null) {
            $iccProfileObject = $this->assertObjectExists($objectsById, $state->iccProfileObjectId, 'ICC profile stream');

            if ($iccProfileObject->streamDictionaryContents === null) {
                throw new DocumentValidationException(
                    DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID,
                    'PDF/A ICC profile must be serialized as a stream object.',
                );
            }
        }

        if ($state->infoObjectId !== null) {
            if ($this->activeProfile?->isPdfA4()) {
                throw new DocumentValidationException(DocumentBuildError::PDFA4_METADATA_INVALID, sprintf(
                    'Profile %s must not serialize an Info dictionary in the final PDF/A-4 object graph.',
                    $this->activeProfile->name(),
                ));
            }

            $this->assertObjectExists($objectsById, $state->infoObjectId, 'info dictionary');
        }
    }

    private function assertPdfA4MetadataObject(IndirectObject $metadataObject): void
    {
        if (!$this->activeProfile?->isPdfA4()) {
            return;
        }

        $metadataContents = $metadataObject->streamContents ?? $metadataObject->contents;

        if (!str_contains($metadataContents, '<pdfaid:part>4</pdfaid:part>')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_METADATA_INVALID, sprintf(
                'Profile %s metadata stream must serialize <pdfaid:part>4</pdfaid:part>.',
                $this->activeProfile->name(),
            ));
        }

        if (!str_contains($metadataContents, '<pdfaid:rev>2020</pdfaid:rev>')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_METADATA_INVALID, sprintf(
                'Profile %s metadata stream must serialize <pdfaid:rev>2020</pdfaid:rev>.',
                $this->activeProfile->name(),
            ));
        }

        $conformance = $this->activeProfile->pdfaConformance();

        if ($conformance === null && str_contains($metadataContents, '<pdfaid:conformance>')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_METADATA_INVALID, sprintf(
                'Profile %s metadata stream must not serialize a pdfaid:conformance marker.',
                $this->activeProfile->name(),
            ));
        }

        if (
            $conformance !== null
            && !str_contains($metadataContents, '<pdfaid:conformance>' . $conformance . '</pdfaid:conformance>')
        ) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_METADATA_INVALID, sprintf(
                'Profile %s metadata stream must serialize <pdfaid:conformance>%s</pdfaid:conformance>.',
                $this->activeProfile->name(),
                $conformance,
            ));
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
        if (
            ($document->profile->isPdfA2() || $document->profile->isPdfA3())
            && !$document->profile->supportsAcroForms()
            && $state->acroFormObjectId !== null
        ) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
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

        $acroForm = $document->acroForm;

        if ($acroForm === null) {
            return;
        }

        foreach ($state->acroFormFieldObjectIds as $fieldIndex => $fieldObjectId) {
            $this->assertReferencePresent(
                $acroFormObject->contents,
                $fieldObjectId,
                sprintf('PDF/A AcroForm must reference field object %d.', $fieldObjectId),
            );
            $fieldObject = $this->assertObjectExists($objectsById, $fieldObjectId, sprintf('AcroForm field %d', $fieldObjectId));
            $field = $acroForm->fields[$fieldIndex] ?? null;

            if (
                $field instanceof PushButtonField
                && $document->profile->isPdfA4()
                && $document->profile->pdfaConformance() === 'E'
                && $field->optionalContentStateAction !== null
            ) {
                $this->assertPdfA4OptionalContentPushButtonAction($document, $state, $field, $fieldObject);
            }
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
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'PDF/A tagged catalog must serialize /MarkInfo << /Marked true >>.',
            );
        }

        $this->assertReferencePresent(
            $catalogObject->contents,
            $state->structTreeRootObjectId,
            'PDF/A tagged catalog must reference the StructTreeRoot object.',
        );

        if ($state->structTreeRootObjectId === null) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'PDF/A tagged catalog requires a StructTreeRoot object ID.',
            );
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
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID,
                'PDF/A catalog must serialize the embedded file name tree when attachments are present.',
            );
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
                throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                    'PDF/A attachment object %d must serialize as a /Filespec dictionary.',
                    $attachmentIndex + 1,
                ));
            }

            if (!str_contains($attachmentObject->contents, '/F ' . $this->pdfString($attachment->filename))) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                    'PDF/A attachment object %d must serialize /F for filename "%s".',
                    $attachmentIndex + 1,
                    $attachment->filename,
                ));
            }

            if (!str_contains($attachmentObject->contents, '/UF ' . $this->pdfString($attachment->filename))) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                    'PDF/A attachment object %d must serialize /UF for filename "%s".',
                    $attachmentIndex + 1,
                    $attachment->filename,
                ));
            }

            if (
                preg_match('/\/EF\s*<<[^>]*\/F\s+' . preg_quote((string) $embeddedFileObjectId, '/') . '\s+0\s+R[^>]*>>/', $attachmentObject->contents) !== 1
            ) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                    'PDF/A attachment object %d must serialize an /EF dictionary that references embedded file stream %d via /F.',
                    $attachmentIndex + 1,
                    $embeddedFileObjectId,
                ));
            }

            if (
                preg_match('/\/EF\s*<<[^>]*\/UF\s+' . preg_quote((string) $embeddedFileObjectId, '/') . '\s+0\s+R[^>]*>>/', $attachmentObject->contents) !== 1
            ) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                    'PDF/A attachment object %d must serialize an /EF dictionary that references embedded file stream %d via /UF.',
                    $attachmentIndex + 1,
                    $embeddedFileObjectId,
                ));
            }

            if ($embeddedFileObject->streamDictionaryContents === null || !str_contains($embeddedFileObject->streamDictionaryContents, '/Type /EmbeddedFile')) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                    'PDF/A embedded file stream %d must serialize as an /EmbeddedFile stream object.',
                    $attachmentIndex + 1,
                ));
            }

            if ($attachment->embeddedFile->mimeType !== null && !str_contains($embeddedFileObject->streamDictionaryContents, '/Subtype /')) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                    'PDF/A embedded file stream %d must serialize /Subtype for MIME-typed attachments.',
                    $attachmentIndex + 1,
                ));
            }

            $relationship = $this->attachmentRelationshipResolver->resolve($document, $attachment);

            if ($relationship !== null && !str_contains($attachmentObject->contents, '/AFRelationship /' . $relationship->value)) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
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
                    throw new DocumentValidationException(DocumentBuildError::PDFA_ASSOCIATED_FILES_NOT_ALLOWED, sprintf(
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
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID,
                'PDF/A catalog must serialize an /AF array for associated files.',
            );
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
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged page object %d must serialize /StructParents.',
                $pageIndex + 1,
            ));
        }

        if ($document->profile->requiresPageAnnotationTabOrder() && ($state->pageAnnotationObjectIds[$pageIndex] ?? []) !== [] && !str_contains($pageObject->contents, '/Tabs /S')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                'PDF/A page object %d must serialize /Tabs /S when annotations are present.',
                $pageIndex + 1,
            ));
        }

        if (
            $document->profile->pdfaConformance() !== 'E'
            || ($document->pages[$pageIndex]->optionalContentGroups === [] && $document->pages[$pageIndex]->optionalContentMemberships === [])
        ) {
            return;
        }

        if (!str_contains($pageObject->contents, '/Properties <<')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                'Profile %s must serialize page resource /Properties for optional content groups on page %d.',
                $document->profile->name(),
                $pageIndex + 1,
            ));
        }

        foreach ($document->pages[$pageIndex]->optionalContentGroups as $alias => $optionalContentGroup) {
            $objectId = $state->optionalContentGroupObjectIds[$optionalContentGroup->key()] ?? null;

            if ($objectId === null) {
                throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                    'Profile %s is missing an allocated optional content group object for alias /%s on page %d.',
                    $document->profile->name(),
                    $alias,
                    $pageIndex + 1,
                ));
            }

            if (!$this->containsPropertyAliasReference($pageObject->contents, $alias, $objectId)) {
                throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                    'Profile %s must serialize page resource alias /%s to optional content group object %d on page %d.',
                    $document->profile->name(),
                    $alias,
                    $objectId,
                    $pageIndex + 1,
                ));
            }
        }

        foreach ($document->pages[$pageIndex]->optionalContentMemberships as $alias => $_optionalContentMembership) {
            $objectId = $state->pageOptionalContentMembershipObjectIds[$pageIndex][$alias] ?? null;

            if ($objectId === null) {
                throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                    'Profile %s is missing an allocated optional content membership object for alias /%s on page %d.',
                    $document->profile->name(),
                    $alias,
                    $pageIndex + 1,
                ));
            }

            if (!$this->containsPropertyAliasReference($pageObject->contents, $alias, $objectId)) {
                throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                    'Profile %s must serialize page resource alias /%s to optional content membership object %d on page %d.',
                    $document->profile->name(),
                    $alias,
                    $objectId,
                    $pageIndex + 1,
                ));
            }
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
                    throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
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

                $this->assertPdfAAnnotationObject($document, $annotation, $annotationObject, $pageIndex, $annotationIndex);

                if (!$this->pdfAAnnotationAppearancePolicy->requiresAppearanceStream($document, $annotation)) {
                    continue;
                }

                $appearanceObjectId = $state->pageAnnotationAppearanceObjectIds[$pageIndex][$annotationIndex] ?? null;

                if ($appearanceObjectId === null) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                        'PDF/A requires a serialized annotation appearance stream object for page annotation %d on page %d.',
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }

                if (!$this->containsAppearanceReference($annotationObject->contents, $appearanceObjectId)) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
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
                    throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                        'PDF/A requires annotation appearance stream %d for page annotation %d on page %d to serialize as a form XObject stream.',
                        $appearanceObjectId,
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }
            }
        }
    }

    private function assertPdfAAnnotationObject(
        Document $document,
        object $annotation,
        IndirectObject $annotationObject,
        int $pageIndex,
        int $annotationIndex,
    ): void {
        if (!$document->profile->isPdfA2() && !$document->profile->isPdfA3() && !$document->profile->isPdfA4()) {
            return;
        }

        $expectedSubtype = match (true) {
            $annotation instanceof LinkAnnotation => 'Link',
            $annotation instanceof TextAnnotation => 'Text',
            $annotation instanceof HighlightAnnotation => 'Highlight',
            $annotation instanceof FreeTextAnnotation => 'FreeText',
            $document->profile->pdfaConformance() === 'E' && $annotation instanceof RichMediaAnnotation => 'RichMedia',
            default => null,
        };

        if ($expectedSubtype === null) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                'Profile %s only supports the current explicit PDF/A-2/3 annotation scope in the final object graph for page annotation %d on page %d.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
            ));
        }

        if (!str_contains($annotationObject->contents, '/Subtype /' . $expectedSubtype)) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                'Profile %s requires page annotation %d on page %d to serialize /Subtype /%s in the final PDF/A object graph.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
                $expectedSubtype,
            ));
        }

        if (!$annotation instanceof LinkAnnotation) {
            if ($annotation instanceof RichMediaAnnotation) {
                $this->assertPdfA4RichMediaAnnotationObject(
                    $document,
                    $annotation,
                    $annotationObject,
                    $pageIndex,
                    $annotationIndex,
                );

                return;
            }

            return;
        }

        if ($annotation->target->isExternalUrl()) {
            if (!str_contains($annotationObject->contents, '/A << /S /URI /URI ')) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ACTION_NOT_ALLOWED, sprintf(
                    'Profile %s requires external link annotation %d on page %d to serialize a URI action in the final PDF/A object graph.',
                    $document->profile->name(),
                    $annotationIndex + 1,
                    $pageIndex + 1,
                ));
            }

            return;
        }

        if (!str_contains($annotationObject->contents, '/Dest ')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_ACTION_NOT_ALLOWED, sprintf(
                'Profile %s requires internal link annotation %d on page %d to serialize a /Dest target in the final PDF/A object graph.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
            ));
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertPdfA4EngineeringObjects(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        IndirectObject $catalogObject,
        array $objectsById,
    ): void {
        if (!$document->profile->isPdfA4()) {
            return;
        }

        if ($document->profile->pdfaConformance() !== 'E' && str_contains($catalogObject->contents, '/OCProperties')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                'Profile %s must not serialize /OCProperties in the current PDF/A-4 object graph.',
                $document->profile->name(),
            ));
        }

        if ($document->profile->pdfaConformance() === 'E') {
            $allowedRichMediaAnnotationObjectIds = [];

            foreach ($document->pages as $pageIndex => $page) {
                foreach ($page->annotations as $annotationIndex => $annotation) {
                    if (!$annotation instanceof RichMediaAnnotation) {
                        continue;
                    }

                    $annotationObjectId = $state->pageAnnotationObjectIds[$pageIndex][$annotationIndex] ?? null;

                    if ($annotationObjectId !== null) {
                        $allowedRichMediaAnnotationObjectIds[$annotationObjectId] = true;
                    }
                }
            }

            foreach ($state->optionalContentGroupObjectIds as $objectId) {
                $object = $this->assertObjectExists($objectsById, $objectId, 'optional content group');

                if (!str_contains($object->contents, '/Type /OCG') || !str_contains($object->contents, '/Name ')) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                        'Profile %s must serialize optional content group object %d as an /OCG dictionary with /Name.',
                        $document->profile->name(),
                        $objectId,
                    ));
                }

                $this->assertReferencePresent(
                    $catalogObject->contents,
                    $objectId,
                    sprintf('Profile %s must reference optional content group object %d in /OCProperties.', $document->profile->name(), $objectId),
                );
            }

            foreach ($document->optionalContentConfigurations as $configuration) {
                if (!str_contains($catalogObject->contents, '/Configs [')) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                        'Profile %s must serialize /Configs when optional content configurations are used.',
                        $document->profile->name(),
                    ));
                }

                if (!str_contains($catalogObject->contents, '/Name ' . $this->pdfString($configuration->name))) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                        'Profile %s must serialize optional content configuration "%s" in /Configs.',
                        $document->profile->name(),
                        $configuration->name,
                    ));
                }

                foreach ($configuration->order as $alias) {
                    $objectId = $this->optionalContentGroupObjectIdByAlias($document, $state, $alias);

                    $this->assertReferencePresent(
                        $catalogObject->contents,
                        $objectId,
                        sprintf(
                            'Profile %s must reference optional content group alias /%s in /Configs.',
                            $document->profile->name(),
                            $alias,
                        ),
                    );
                }
            }

            foreach ($document->pages as $page) {
                foreach ($page->optionalContentGroups as $optionalContentGroup) {
                    $objectId = $state->optionalContentGroupObjectIds[$optionalContentGroup->key()] ?? null;

                    if ($objectId === null) {
                        continue;
                    }

                    $reference = $objectId . ' 0 R';

                    if ($optionalContentGroup->visible && !str_contains($catalogObject->contents, '/ON [')) {
                        throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                            'Profile %s must serialize /ON for visible optional content groups.',
                            $document->profile->name(),
                        ));
                    }

                    if (!$optionalContentGroup->visible && !str_contains($catalogObject->contents, '/OFF [')) {
                        throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                            'Profile %s must serialize /OFF for initially hidden optional content groups.',
                            $document->profile->name(),
                        ));
                    }

                    if ($optionalContentGroup->visible && !$this->containsOptionalContentListReference($catalogObject->contents, 'ON', $reference)) {
                        throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                            'Profile %s must list visible optional content group object %d in /ON.',
                            $document->profile->name(),
                            $objectId,
                        ));
                    }

                    if (!$optionalContentGroup->visible && !$this->containsOptionalContentListReference($catalogObject->contents, 'OFF', $reference)) {
                        throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                            'Profile %s must list hidden optional content group object %d in /OFF.',
                            $document->profile->name(),
                            $objectId,
                        ));
                    }
                }
            }

            foreach ($document->pages as $pageIndex => $page) {
                foreach ($page->optionalContentMemberships as $alias => $membership) {
                    $objectId = $state->pageOptionalContentMembershipObjectIds[$pageIndex][$alias] ?? null;

                    if ($objectId === null) {
                        throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                            'Profile %s must allocate an optional content membership object for alias /%s on page %d.',
                            $document->profile->name(),
                            $alias,
                            $pageIndex + 1,
                        ));
                    }

                    $object = $this->assertObjectExists($objectsById, $objectId, 'optional content membership');

                    if (!str_contains($object->contents, '/Type /OCMD') || !str_contains($object->contents, '/OCGs [')) {
                        throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                            'Profile %s must serialize optional content membership object %d as an /OCMD dictionary with /OCGs.',
                            $document->profile->name(),
                            $objectId,
                        ));
                    }

                    foreach ($membership->groupAliases as $groupAlias) {
                        $group = $page->optionalContentGroups[$groupAlias] ?? null;

                        if ($group === null) {
                            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                                'Profile %s must resolve optional content membership alias /%s to an existing optional content group on page %d.',
                                $document->profile->name(),
                                $groupAlias,
                                $pageIndex + 1,
                            ));
                        }

                        $groupObjectId = $state->optionalContentGroupObjectIds[$group->key()] ?? null;

                        $this->assertReferencePresent(
                            $object->contents,
                            $groupObjectId,
                            sprintf(
                                'Profile %s must reference optional content group objects for membership alias /%s on page %d.',
                                $document->profile->name(),
                                $alias,
                                $pageIndex + 1,
                            ),
                        );
                    }

                    if ($membership->visibilityExpression !== null) {
                        $expectedExpression = $this->serializeOptionalContentVisibilityExpression(
                            $membership->visibilityExpression,
                            $page->optionalContentGroups,
                            $state,
                        );

                        if (!str_contains($object->contents, '/VE ' . $expectedExpression)) {
                            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                                'Profile %s must serialize optional content membership object %d with the configured /VE expression.',
                                $document->profile->name(),
                                $objectId,
                            ));
                        }
                    } elseif (!str_contains($object->contents, '/P /AnyOn') && !str_contains($object->contents, '/P /AllOn')) {
                        throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                            'Profile %s must serialize optional content membership object %d with /P /AnyOn or /P /AllOn.',
                            $document->profile->name(),
                            $objectId,
                        ));
                    }
                }
            }
        }

        foreach ($objectsById as $object) {
            if (
                ($document->profile->pdfaConformance() !== 'E' && str_contains($object->contents, '/Subtype /RichMedia'))
                || ($document->profile->pdfaConformance() === 'E'
                    && str_contains($object->contents, '/Subtype /RichMedia')
                    && !isset($allowedRichMediaAnnotationObjectIds[$object->objectId]))
            ) {
                throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                    'Profile %s must not serialize RichMedia annotations or assets in the current PDF/A-4 object graph.',
                    $document->profile->name(),
                ));
            }

            if (str_contains($object->contents, '/Subtype /3D')) {
                throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                    'Profile %s must not serialize 3D annotations in the current PDF/A-4 object graph.',
                    $document->profile->name(),
                ));
            }
        }
    }

    private function assertReferencePresent(string $contents, ?int $objectId, string $message): void
    {
        if ($objectId === null) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, $message);
        }

        if (preg_match('/\b' . preg_quote((string) $objectId, '/') . '\s+0\s+R\b/', $contents) !== 1) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, $message);
        }
    }

    private function containsAppearanceReference(string $contents, int $appearanceObjectId): bool
    {
        return preg_match(
            '/\/AP\s*<<\s*\/N\s+' . preg_quote((string) $appearanceObjectId, '/') . '\s+0\s+R\s*>>/',
            $contents,
        ) === 1;
    }

    private function containsOptionalContentListReference(string $contents, string $listName, string $reference): bool
    {
        return preg_match(
            '/\/' . preg_quote($listName, '/') . '\s*\[[^\]]*\b' . preg_quote($reference, '/') . '\b[^\]]*\]/',
            $contents,
        ) === 1;
    }

    private function containsPropertyAliasReference(string $contents, string $alias, int $objectId): bool
    {
        return preg_match(
            '/\/Properties\s*<<[^>]*\/' . preg_quote($alias, '/') . '\s+' . preg_quote((string) $objectId, '/') . '\s+0\s+R\b[^>]*>>/',
            $contents,
        ) === 1;
    }

    /**
     * @param array<string, OptionalContentGroup> $pageOptionalContentGroups
     */
    private function serializeOptionalContentVisibilityExpression(
        OptionalContentVisibilityExpression $expression,
        array $pageOptionalContentGroups,
        DocumentSerializationPlanBuildState $state,
    ): string {
        if ($expression->isAlias()) {
            $group = $pageOptionalContentGroups[$expression->groupAlias() ?? ''] ?? null;
            $objectId = $group === null ? null : ($state->optionalContentGroupObjectIds[$group->key()] ?? null);

            return $objectId === null ? 'null' : $objectId . ' 0 R';
        }

        return '[' . $expression->operatorToken() . ' ' . implode(' ', array_map(
            fn (OptionalContentVisibilityExpression $operand): string => $this->serializeOptionalContentVisibilityExpression($operand, $pageOptionalContentGroups, $state),
            $expression->operands(),
        )) . ']';
    }

    private function profileLabel(): string
    {
        return $this->activeProfile?->name() ?? 'PDF/A';
    }

    private function optionalContentGroupObjectIdByAlias(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        string $alias,
    ): ?int {
        foreach ($document->pages as $page) {
            $group = $page->optionalContentGroups[$alias] ?? null;

            if ($group === null) {
                continue;
            }

            return $state->optionalContentGroupObjectIds[$group->key()] ?? null;
        }

        return null;
    }

    private function assertPdfA4OptionalContentPushButtonAction(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        PushButtonField $field,
        IndirectObject $fieldObject,
    ): void {
        $stateAction = $field->optionalContentStateAction;

        if ($stateAction === null) {
            return;
        }

        if (!str_contains($fieldObject->contents, '/A << /S /SetOCGState ')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                'Profile %s requires push button field "%s" to serialize a /SetOCGState action.',
                $document->profile->name(),
                $field->name,
            ));
        }

        if (!str_contains($fieldObject->contents, '/State [')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                'Profile %s requires push button field "%s" to serialize a /State array in /SetOCGState.',
                $document->profile->name(),
                $field->name,
            ));
        }

        foreach ([
            'ON' => $stateAction->turnOn,
            'OFF' => $stateAction->turnOff,
            'Toggle' => $stateAction->toggle,
        ] as $stateToken => $aliases) {
            foreach ($aliases as $alias) {
                $page = $document->pages[$field->pageNumber - 1] ?? null;
                $group = $page?->optionalContentGroups[$alias] ?? null;
                $groupObjectId = $group === null ? null : ($state->optionalContentGroupObjectIds[$group->key()] ?? null);

                if ($groupObjectId === null || !preg_match('/\/' . preg_quote($stateToken, '/') . '\s+' . preg_quote((string) $groupObjectId, '/') . '\s+0\s+R\b/', $fieldObject->contents)) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                        'Profile %s requires push button field "%s" to serialize /%s %d 0 R in /SetOCGState.',
                        $document->profile->name(),
                        $field->name,
                        $stateToken,
                        $groupObjectId ?? 0,
                    ));
                }
            }
        }
    }

    private function assertPdfA4RichMediaAnnotationObject(
        Document $document,
        RichMediaAnnotation $annotation,
        IndirectObject $annotationObject,
        int $pageIndex,
        int $annotationIndex,
    ): void {
        if (!str_contains($annotationObject->contents, '/RichMediaContent ') || !str_contains($annotationObject->contents, '/RichMediaSettings ')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                'Profile %s requires RichMedia annotation %d on page %d to serialize /RichMediaContent and /RichMediaSettings.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
            ));
        }

        if (!str_contains($annotationObject->contents, '/AP << /N ')) {
            throw new DocumentValidationException(DocumentBuildError::PDFA4_ENGINEERING_FEATURE_NOT_ALLOWED, sprintf(
                'Profile %s requires RichMedia annotation %d on page %d to serialize a poster appearance stream.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
            ));
        }
    }
}
