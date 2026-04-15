<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function ksort;
use function sprintf;

use Kalle\Pdf\Document\TaggedPdf\ParentTree;
use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Document\TaggedPdf\StructTreeRoot;
use Kalle\Pdf\Writer\IndirectObject;

final readonly class TaggedStructTreeScaffoldObjectBuilder
{
    public function __construct(
        private TaggedStructureLayoutPolicy $taggedStructureLayoutPolicy = new TaggedStructureLayoutPolicy(),
    ) {
    }

    /**
     * @return list<IndirectObject>
     */
    public function buildObjects(DocumentSerializationPlanBuildState $state): array
    {
        if ($state->structTreeRootObjectId === null || $state->documentStructElemObjectId === null) {
            return [];
        }

        $objects = [];
        $documentKidObjectIds = $this->documentKidObjectIds($state);
        $roleMap = $state->taggedPageAnnotationStructure['entries'] !== []
            ? ['Annot' => 'Span']
            : [];

        $objects[] = new IndirectObject(
            $state->structTreeRootObjectId,
            new StructTreeRoot([$state->documentStructElemObjectId], $state->parentTreeObjectId, $roleMap)->objectContents(),
        );
        $objects[] = new IndirectObject(
            $state->documentStructElemObjectId,
            new StructElem('Document', $state->structTreeRootObjectId, $documentKidObjectIds)->objectContents(),
        );

        foreach ($state->taggedStructure->containerEntries as $containerEntry) {
            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->genericStructElemObjectIds[$containerEntry['key']],
                new StructElem(
                    $containerEntry['tag'],
                    $this->resolveParentObjectId($containerEntry['key'], $state),
                    $this->containerKidObjectIds($containerEntry['childKeys'], $state),
                )->objectContents(),
            );
        }

        if ($state->parentTreeObjectId !== null) {
            $objects[] = new IndirectObject(
                $state->parentTreeObjectId,
                new ParentTree($this->parentTreeEntries($state))->objectContents(),
            );
        }

        return $objects;
    }

    /**
     * @return array<int, list<int>>
     */
    private function parentTreeEntries(DocumentSerializationPlanBuildState $state): array
    {
        $parentTreeEntries = [];

        foreach ($state->pageStructParentIds as $pageIndex => $structParentId) {
            $pageKeys = $state->taggedStructure->pageMarkedContentKeys[$pageIndex] ?? [];

            if ($pageKeys === []) {
                continue;
            }

            ksort($pageKeys);
            $parentTreeEntries[$structParentId] = $this->pageParentTreeEntry($pageKeys, $state);
        }

        foreach ($state->taggedLinkStructure['parentTreeEntries'] as $structParentId => $linkKeys) {
            $parentTreeEntries[$structParentId] = $this->lookupObjectIds($linkKeys, $state->taggedStructureObjectIds->linkStructElemObjectIds);
        }

        foreach ($state->taggedPageAnnotationStructure['parentTreeEntries'] as $structParentId => $annotationKeys) {
            $parentTreeEntries[$structParentId] = $this->lookupObjectIds($annotationKeys, $state->taggedStructureObjectIds->annotationStructElemObjectIds);
        }

        foreach ($state->taggedFormStructure['parentTreeEntries'] as $structParentId => $formKeys) {
            $parentTreeEntries[$structParentId] = $this->lookupObjectIds($formKeys, $state->taggedFormStructElemObjectIds);
        }

        return $parentTreeEntries;
    }

    /**
     * @return list<int>
     */
    private function documentKidObjectIds(DocumentSerializationPlanBuildState $state): array
    {
        $objectIds = [];

        foreach ($this->taggedStructureLayoutPolicy->orderedDocumentChildKeys($state) as $key) {
            $objectIds[] = $this->resolveDocumentKidObjectId($key, $state);
        }

        return $objectIds;
    }

    /**
     * @param list<string> $childKeys
     * @return list<int>
     */
    private function containerKidObjectIds(array $childKeys, DocumentSerializationPlanBuildState $state): array
    {
        $objectIds = [];

        foreach ($childKeys as $childKey) {
            $objectIds[] = $state->taggedStructureObjectIds->resolveStructElemObjectId($childKey);
        }

        return $objectIds;
    }

    /**
     * @param array<int, string> $pageKeys
     * @return list<int>
     */
    private function pageParentTreeEntry(array $pageKeys, DocumentSerializationPlanBuildState $state): array
    {
        $objectIds = [];

        foreach ($pageKeys as $key) {
            $objectIds[] = $state->taggedStructureObjectIds->resolvePageContentObjectId($key);
        }

        return $objectIds;
    }

    /**
     * @param list<string> $keys
     * @param array<string, int> $objectIdsByKey
     * @return list<int>
     */
    private function lookupObjectIds(array $keys, array $objectIdsByKey): array
    {
        $objectIds = [];

        foreach ($keys as $key) {
            $objectIds[] = $objectIdsByKey[$key];
        }

        return $objectIds;
    }

    private function resolveDocumentKidObjectId(string $key, DocumentSerializationPlanBuildState $state): int
    {
        return $state->taggedFormStructElemObjectIds[$key] ?? $state->taggedStructureObjectIds->resolveStructElemObjectId($key);
    }

    public function resolveParentObjectId(string $key, DocumentSerializationPlanBuildState $state): int
    {
        $parentKey = $this->taggedStructureLayoutPolicy->explicitParentKey($key, $state);

        if ($parentKey === null) {
            return $this->documentStructElemObjectId($state);
        }

        return $state->taggedStructureObjectIds->genericStructElemObjectIds[$parentKey]
            ?? throw new DocumentValidationException(
                DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID,
                sprintf('Unknown tagged structure parent key "%s".', $parentKey),
            );
    }

    private function documentStructElemObjectId(DocumentSerializationPlanBuildState $state): int
    {
        return $state->documentStructElemObjectId
            ?? throw new DocumentValidationException(
                DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID,
                'Tagged document root object id is missing.',
            );
    }
}
