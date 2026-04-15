<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Writer\IndirectObject;

final readonly class DocumentTaggedPdfObjectBuilder
{
    public function __construct(
        private TaggedLinkStructureCollector $taggedLinkStructureCollector = new TaggedLinkStructureCollector(),
        private TaggedPageAnnotationStructureCollector $taggedPageAnnotationStructureCollector = new TaggedPageAnnotationStructureCollector(),
        private TaggedFormStructureCollector $taggedFormStructureCollector = new TaggedFormStructureCollector(),
        private TaggedStructElemObjectBuilder $taggedStructElemObjectBuilder = new TaggedStructElemObjectBuilder(),
    ) {
    }

    /**
     * @return array{
     *   linkEntries: list<array{
     *     key: string,
     *     pageIndex: int,
     *     annotationIndices: list<int>,
     *     altText: string,
     *     markedContentIds: list<int>
     *   }>,
     *   parentTreeEntries: array<int, list<string>>,
     *   structParentIds: array<string, int>,
     *   nextStructParentId: int
     * }
     */
    public function collectTaggedLinkStructure(Document $document, int $nextStructParentId): array
    {
        return $this->taggedLinkStructureCollector->collect($document, $nextStructParentId);
    }

    /**
     * @return array{
     *   entries: list<array{
     *     key: string,
     *     pageIndex: int,
     *     annotationIndex: int,
     *     altText: string,
     *     tag: string
     *   }>,
     *   parentTreeEntries: array<int, list<string>>,
     *   structParentIds: array<string, int>,
     *   nextStructParentId: int
     * }
     */
    public function collectTaggedPageAnnotationStructure(Document $document, int $nextStructParentId): array
    {
        return $this->taggedPageAnnotationStructureCollector->collect($document, $nextStructParentId);
    }

    /**
     * @param list<int> $acroFormFieldObjectIds
     * @param array<int, list<int>> $acroFormFieldRelatedObjectIds
     * @return array{
     *   entries: list<array{key: string, pageIndex: int, annotationObjectId: int, altText: string}>,
     *   parentTreeEntries: array<int, list<string>>,
     *   structParentIds: array<int, int>
     * }
     */
    public function collectTaggedFormStructure(
        Document $document,
        array $acroFormFieldObjectIds,
        array $acroFormFieldRelatedObjectIds,
        int $nextStructParentId,
    ): array {
        return $this->taggedFormStructureCollector->collect(
            $document,
            $acroFormFieldObjectIds,
            $acroFormFieldRelatedObjectIds,
            $nextStructParentId,
        );
    }

    /**
     * @return list<IndirectObject>
     */
    public function buildObjects(Document $document, DocumentSerializationPlanBuildState $state): array
    {
        return $this->taggedStructElemObjectBuilder->buildObjects($document, $state);
    }
}
