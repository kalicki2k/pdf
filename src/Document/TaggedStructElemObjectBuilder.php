<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_map;
use function count;

use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Writer\IndirectObject;

final readonly class TaggedStructElemObjectBuilder
{
    public function __construct(
        private TaggedStructTreeScaffoldObjectBuilder $taggedStructTreeScaffoldObjectBuilder = new TaggedStructTreeScaffoldObjectBuilder(),
        private TaggedTableStructElemObjectBuilder $taggedTableStructElemObjectBuilder = new TaggedTableStructElemObjectBuilder(),
        private TaggedListStructElemObjectBuilder $taggedListStructElemObjectBuilder = new TaggedListStructElemObjectBuilder(),
        private TaggedAnnotationStructElemObjectBuilder $taggedAnnotationStructElemObjectBuilder = new TaggedAnnotationStructElemObjectBuilder(),
    ) {
    }

    /**
     * @return list<IndirectObject>
     */
    public function buildObjects(Document $document, DocumentSerializationPlanBuildState $state): array
    {
        $objects = $this->taggedStructTreeScaffoldObjectBuilder->buildObjects($state);

        foreach ($state->taggedStructure->figureEntries as $figureEntry) {
            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->figureStructElemObjectIds[$figureEntry['key']],
                new StructElem(
                    'Figure',
                    $this->resolveParentObjectId($figureEntry['key'], $state),
                    pageObjectId: $state->pageObjectIds[$figureEntry['pageIndex']],
                    altText: $figureEntry['altText'],
                    markedContentId: $figureEntry['markedContentId'],
                )->objectContents(),
            );
        }

        foreach ($state->taggedStructure->textEntries as $textEntry) {
            $references = $textEntry['references'];
            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->textStructElemObjectIds[$textEntry['key']],
                new StructElem(
                    $textEntry['tag'],
                    $this->resolveParentObjectId($textEntry['key'], $state),
                    pageObjectId: count($references) === 1 ? $state->pageObjectIds[$references[0]['pageIndex']] : null,
                    markedContentId: count($references) === 1 ? $references[0]['markedContentId'] : null,
                    kidEntries: count($references) > 1
                        ? $this->taggedMarkedContentKidEntries(
                            array_map(
                                static fn (array $reference): object => (object) $reference,
                                $references,
                            ),
                            $state->pageObjectIds,
                        )
                        : null,
                )->objectContents(),
            );
        }

        foreach ($state->taggedStructure->listEntries as $listEntry) {
            $objects = [...$objects, ...$this->taggedListStructElemObjectBuilder->buildObjects(
                $listEntry,
                $state,
                $this->resolveParentObjectId($listEntry['key'], $state),
                $this->taggedMarkedContentKidEntries(...),
            )];
        }

        foreach ($document->taggedTables as $taggedTable) {
            $objects = [...$objects, ...$this->taggedTableStructElemObjectBuilder->buildObjects(
                $document,
                $taggedTable,
                $state,
                $this->resolveParentObjectId($taggedTable->key ?? 'table:' . $taggedTable->tableId, $state),
            )];
        }

        foreach ($state->taggedLinkStructure['linkEntries'] as $linkEntry) {
            $objects[] = $this->taggedAnnotationStructElemObjectBuilder->buildLinkObject(
                $linkEntry,
                $state,
                $this->resolveParentObjectId($linkEntry['key'], $state),
            );
        }

        foreach ($state->taggedPageAnnotationStructure['entries'] as $annotationEntry) {
            $objects[] = $this->taggedAnnotationStructElemObjectBuilder->buildPageAnnotationObject(
                $annotationEntry,
                $state,
                $this->documentStructElemObjectId($state),
            );
        }

        foreach ($state->taggedFormStructure['entries'] as $formEntry) {
            $objects[] = $this->taggedAnnotationStructElemObjectBuilder->buildFormObject(
                $formEntry,
                $state,
                $this->documentStructElemObjectId($state),
            );
        }

        return $objects;
    }

    private function documentStructElemObjectId(DocumentSerializationPlanBuildState $state): int
    {
        return $state->documentStructElemObjectId
            ?? throw new DocumentValidationException(
                DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID,
                'Tagged document root object id is missing.',
            );
    }

    private function resolveParentObjectId(string $key, DocumentSerializationPlanBuildState $state): int
    {
        return $this->taggedStructTreeScaffoldObjectBuilder->resolveParentObjectId($key, $state);
    }

    /**
     * @param list<object{pageIndex: int, markedContentId: int}> $references
     * @param list<int> $pageObjectIds
     * @return list<string>
     */
    private function taggedMarkedContentKidEntries(array $references, array $pageObjectIds): array
    {
        return array_map(
            static fn (object $reference): string => '<< /Type /MCR /Pg '
                . $pageObjectIds[$reference->pageIndex]
                . ' 0 R /MCID '
                . $reference->markedContentId
                . ' >>',
            $references,
        );
    }
}
