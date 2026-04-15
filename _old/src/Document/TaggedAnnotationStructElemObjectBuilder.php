<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Writer\IndirectObject;

final class TaggedAnnotationStructElemObjectBuilder
{
    /**
     * @param array{key: string, pageIndex: int, annotationIndices: list<int>, altText: string, markedContentIds: list<int>} $linkEntry
     */
    public function buildLinkObject(
        array $linkEntry,
        DocumentSerializationPlanBuildState $state,
        int $parentObjectId,
    ): IndirectObject {
        $pageObjectId = $state->pageObjectIds[$linkEntry['pageIndex']];
        $kidEntries = [];

        foreach ($linkEntry['markedContentIds'] as $markedContentId) {
            $kidEntries[] = (string) $markedContentId;
        }

        foreach ($linkEntry['annotationIndices'] as $annotationIndex) {
            $annotationObjectId = $state->pageAnnotationObjectIds[$linkEntry['pageIndex']][$annotationIndex];
            $kidEntries[] = '<< /Type /OBJR /Obj ' . $annotationObjectId . ' 0 R /Pg ' . $pageObjectId . ' 0 R >>';
        }

        return IndirectObject::plain(
            $state->taggedStructureObjectIds->linkStructElemObjectIds[$linkEntry['key']],
            new StructElem(
                'Link',
                $parentObjectId,
                pageObjectId: $pageObjectId,
                altText: $linkEntry['altText'],
                kidEntries: $kidEntries,
            )->objectContents(),
        );
    }

    /**
     * @param array{key: string, pageIndex: int, annotationIndex: int, altText: string, tag: string} $annotationEntry
     */
    public function buildPageAnnotationObject(
        array $annotationEntry,
        DocumentSerializationPlanBuildState $state,
        int $documentStructElemObjectId,
    ): IndirectObject {
        $pageObjectId = $state->pageObjectIds[$annotationEntry['pageIndex']];
        $annotationObjectId = $state->pageAnnotationObjectIds[$annotationEntry['pageIndex']][$annotationEntry['annotationIndex']];

        return IndirectObject::plain(
            $state->taggedStructureObjectIds->annotationStructElemObjectIds[$annotationEntry['key']],
            new StructElem(
                $annotationEntry['tag'],
                $documentStructElemObjectId,
                pageObjectId: $pageObjectId,
                altText: $annotationEntry['altText'],
                kidEntries: [
                    '<< /Type /OBJR /Obj '
                    . $annotationObjectId
                    . ' 0 R /Pg '
                    . $pageObjectId
                    . ' 0 R >>',
                ],
            )->objectContents(),
        );
    }

    /**
     * @param array{key: string, pageIndex: int, annotationObjectId: int, altText: string} $formEntry
     */
    public function buildFormObject(
        array $formEntry,
        DocumentSerializationPlanBuildState $state,
        int $documentStructElemObjectId,
    ): IndirectObject {
        $pageObjectId = $state->pageObjectIds[$formEntry['pageIndex']];

        return IndirectObject::plain(
            $state->taggedFormStructElemObjectIds[$formEntry['key']],
            new StructElem(
                'Form',
                $documentStructElemObjectId,
                pageObjectId: $pageObjectId,
                altText: $formEntry['altText'],
                kidEntries: [
                    '<< /Type /OBJR /Obj '
                    . $formEntry['annotationObjectId']
                    . ' 0 R /Pg '
                    . $pageObjectId
                    . ' 0 R >>',
                ],
            )->objectContents(),
        );
    }
}
