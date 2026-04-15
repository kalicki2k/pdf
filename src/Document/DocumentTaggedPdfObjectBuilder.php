<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function sprintf;

use Kalle\Pdf\Document\TaggedPdf\ParentTree;
use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Document\TaggedPdf\StructTreeRoot;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureObjectIds;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Writer\IndirectObject;

final readonly class DocumentTaggedPdfObjectBuilder
{
    public function __construct(
        private TaggedLinkStructureCollector $taggedLinkStructureCollector = new TaggedLinkStructureCollector(),
        private TaggedPageAnnotationStructureCollector $taggedPageAnnotationStructureCollector = new TaggedPageAnnotationStructureCollector(),
        private TaggedFormStructureCollector $taggedFormStructureCollector = new TaggedFormStructureCollector(),
        private TaggedStructureLayoutPolicy $taggedStructureLayoutPolicy = new TaggedStructureLayoutPolicy(),
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

            $objects[] = new IndirectObject($state->parentTreeObjectId, new ParentTree($parentTreeEntries)->objectContents());
        }

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
            $listKidObjectIds = [];

            foreach ($listEntry['itemEntries'] as $itemEntry) {
                $listKidObjectIds[] = $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']];
            }

            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->listStructElemObjectIds[$listEntry['key']],
                new StructElem('L', $this->resolveParentObjectId($listEntry['key'], $state), $listKidObjectIds)->objectContents(),
            );

            foreach ($listEntry['itemEntries'] as $itemEntry) {
                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']],
                    new StructElem(
                        'LI',
                        $state->taggedStructureObjectIds->listStructElemObjectIds[$listEntry['key']],
                        [
                            $state->taggedStructureObjectIds->listLabelStructElemObjectIds[$itemEntry['labelKey']],
                            $state->taggedStructureObjectIds->listBodyStructElemObjectIds[$itemEntry['bodyKey']],
                        ],
                    )->objectContents(),
                );
                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->listLabelStructElemObjectIds[$itemEntry['labelKey']],
                    new StructElem(
                        'Lbl',
                        $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']],
                        kidEntries: $this->taggedMarkedContentKidEntries([$itemEntry['labelReference']], $state->pageObjectIds),
                    )->objectContents(),
                );
                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->listBodyStructElemObjectIds[$itemEntry['bodyKey']],
                    new StructElem(
                        'LBody',
                        $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']],
                        kidEntries: $this->taggedMarkedContentKidEntries([$itemEntry['bodyReference']], $state->pageObjectIds),
                    )->objectContents(),
                );
            }
        }

        foreach ($document->taggedTables as $taggedTable) {
            $tableStructKey = $taggedTable->key ?? TaggedStructureObjectIds::tableKey($taggedTable->tableId);
            $tableKidObjectIds = [];

            if ($taggedTable->hasCaption()) {
                $tableKidObjectIds[] = $state->taggedStructureObjectIds->captionStructElemObjectIds[
                    TaggedStructureObjectIds::tableCaptionKey($taggedTable->tableId)
                ];
            }

            foreach ($this->taggedTableSections($taggedTable) as $section => $rows) {
                if ($rows !== []) {
                    $tableKidObjectIds[] = $state->taggedStructureObjectIds->tableSectionStructElemObjectIds[
                        TaggedStructureObjectIds::tableSectionKey($taggedTable->tableId, $section)
                    ];
                }
            }

            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->tableStructElemObjectIds[$tableStructKey],
                new StructElem('Table', $this->resolveParentObjectId($tableStructKey, $state), $tableKidObjectIds)->objectContents(),
            );

            if ($taggedTable->hasCaption()) {
                $captionKey = TaggedStructureObjectIds::tableCaptionKey($taggedTable->tableId);
                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->captionStructElemObjectIds[$captionKey],
                    new StructElem(
                        'Caption',
                        $state->taggedStructureObjectIds->tableStructElemObjectIds[$tableStructKey],
                        kidEntries: $this->taggedMarkedContentKidEntries($taggedTable->captionReferences, $state->pageObjectIds),
                    )->objectContents(),
                );
            }

            foreach ($this->taggedTableSections($taggedTable) as $section => $rows) {
                if ($rows === []) {
                    continue;
                }

                $sectionKey = TaggedStructureObjectIds::tableSectionKey($taggedTable->tableId, $section);
                $sectionKidObjectIds = [];

                foreach ($rows as $row) {
                    $sectionKidObjectIds[] = $state->taggedStructureObjectIds->rowStructElemObjectIds[TaggedStructureObjectIds::tableRowKey(
                        $taggedTable->tableId,
                        $section,
                        $row->rowIndex,
                    )];
                }

                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->tableSectionStructElemObjectIds[$sectionKey],
                    new StructElem(
                        $this->taggedTableSectionTag($document, $section),
                        $state->taggedStructureObjectIds->tableStructElemObjectIds[$tableStructKey],
                        $sectionKidObjectIds,
                    )->objectContents(),
                );

                foreach ($rows as $row) {
                    $rowKey = TaggedStructureObjectIds::tableRowKey($taggedTable->tableId, $section, $row->rowIndex);
                    $rowKidObjectIds = [];

                    foreach ($row->cells as $cell) {
                        $rowKidObjectIds[] = $state->taggedStructureObjectIds->cellStructElemObjectIds[TaggedStructureObjectIds::tableCellKey(
                            $taggedTable->tableId,
                            $section,
                            $row->rowIndex,
                            $cell->columnIndex,
                        )];
                    }

                    $objects[] = new IndirectObject(
                        $state->taggedStructureObjectIds->rowStructElemObjectIds[$rowKey],
                        new StructElem('TR', $state->taggedStructureObjectIds->tableSectionStructElemObjectIds[$sectionKey], $rowKidObjectIds)->objectContents(),
                    );

                    foreach ($row->cells as $cell) {
                        $cellKey = TaggedStructureObjectIds::tableCellKey($taggedTable->tableId, $section, $row->rowIndex, $cell->columnIndex);
                        $objects[] = new IndirectObject(
                            $state->taggedStructureObjectIds->cellStructElemObjectIds[$cellKey],
                            new StructElem(
                                $cell->header ? 'TH' : 'TD',
                                $state->taggedStructureObjectIds->rowStructElemObjectIds[$rowKey],
                                kidEntries: $this->taggedMarkedContentKidEntries($cell->contentReferences, $state->pageObjectIds),
                                scope: $cell->headerScope?->value,
                                rowSpan: $cell->rowspan > 1 ? $cell->rowspan : null,
                                colSpan: $cell->colspan > 1 ? $cell->colspan : null,
                            )->objectContents(),
                        );
                    }
                }
            }
        }

        foreach ($state->taggedLinkStructure['linkEntries'] as $linkEntry) {
            $pageObjectId = $state->pageObjectIds[$linkEntry['pageIndex']];
            $kidEntries = [];

            foreach ($linkEntry['markedContentIds'] as $markedContentId) {
                $kidEntries[] = (string) $markedContentId;
            }

            foreach ($linkEntry['annotationIndices'] as $annotationIndex) {
                $annotationObjectId = $state->pageAnnotationObjectIds[$linkEntry['pageIndex']][$annotationIndex];
                $kidEntries[] = '<< /Type /OBJR /Obj ' . $annotationObjectId . ' 0 R /Pg ' . $pageObjectId . ' 0 R >>';
            }

            $objects[] = IndirectObject::plain(
                $state->taggedStructureObjectIds->linkStructElemObjectIds[$linkEntry['key']],
                new StructElem(
                    'Link',
                    $this->resolveParentObjectId($linkEntry['key'], $state),
                    pageObjectId: $pageObjectId,
                    altText: $linkEntry['altText'],
                    kidEntries: $kidEntries,
                )->objectContents(),
            );
        }

        foreach ($state->taggedPageAnnotationStructure['entries'] as $annotationEntry) {
            $pageObjectId = $state->pageObjectIds[$annotationEntry['pageIndex']];
            $annotationObjectId = $state->pageAnnotationObjectIds[$annotationEntry['pageIndex']][$annotationEntry['annotationIndex']];

            $objects[] = IndirectObject::plain(
                $state->taggedStructureObjectIds->annotationStructElemObjectIds[$annotationEntry['key']],
                new StructElem(
                    $annotationEntry['tag'],
                    $state->documentStructElemObjectId,
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

        foreach ($state->taggedFormStructure['entries'] as $formEntry) {
            $pageObjectId = $state->pageObjectIds[$formEntry['pageIndex']];

            $objects[] = IndirectObject::plain(
                $state->taggedFormStructElemObjectIds[$formEntry['key']],
                new StructElem(
                    'Form',
                    $state->documentStructElemObjectId,
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

        return $objects;
    }

    private function resolveDocumentKidObjectId(string $key, DocumentSerializationPlanBuildState $state): int
    {
        return $state->taggedFormStructElemObjectIds[$key] ?? $state->taggedStructureObjectIds->resolveStructElemObjectId($key);
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

    private function resolveParentObjectId(string $key, DocumentSerializationPlanBuildState $state): int
    {
        $parentKey = $this->taggedStructureLayoutPolicy->explicitParentKey($key, $state);

        if ($parentKey === null) {
            return $state->documentStructElemObjectId
                ?? throw new DocumentValidationException(
                    DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID,
                    'Tagged document root object id is missing.',
                );
        }

        return $state->taggedStructureObjectIds->genericStructElemObjectIds[$parentKey]
            ?? throw new DocumentValidationException(
                DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID,
                sprintf('Unknown tagged structure parent key "%s".', $parentKey),
            );
    }

    /**
     * @return array<string, list<TaggedTableRow>>
     */
    private function taggedTableSections(TaggedTable $taggedTable): array
    {
        return [
            'header' => $taggedTable->headerRows,
            'body' => $taggedTable->bodyRows,
            'footer' => $taggedTable->footerRows,
        ];
    }

    private function taggedTableSectionTag(Document $document, string $section): string
    {
        if ($document->profile->isPdfA1()) {
            return 'Sect';
        }

        return match ($section) {
            'header' => 'THead',
            'footer' => 'TFoot',
            default => 'TBody',
        };
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
