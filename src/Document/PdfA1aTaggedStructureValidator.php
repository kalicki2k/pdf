<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_key_exists;
use function array_map;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function sprintf;
use function str_contains;

use Kalle\Pdf\Document\TaggedPdf\TaggedStructureObjectIds;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Writer\IndirectObject;

final class PdfA1aTaggedStructureValidator
{
    public function __construct(
        private readonly TaggedStructureLayoutPolicy $taggedStructureLayoutPolicy = new TaggedStructureLayoutPolicy(),
    ) {
    }

    /**
     * @param list<IndirectObject> $objects
     */
    public function assertValid(Document $document, DocumentSerializationPlanBuildState $state, array $objects): void
    {
        if (!$document->profile->isPdfA() || $document->profile->pdfaConformance() !== 'A') {
            return;
        }

        if ($state->structTreeRootObjectId === null || $state->documentStructElemObjectId === null) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'Profile PDF/A tagged structure requires StructTreeRoot and Document structure objects.',
            );
        }

        $objectsById = $this->indexObjectsById($objects);
        $expectedParentTreeEntries = $this->expectedParentTreeEntries($state);
        $parentTreeRequired = $expectedParentTreeEntries !== [];

        if ($parentTreeRequired && $state->parentTreeObjectId === null) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'Profile PDF/A tagged structure requires a ParentTree for structured marked content.',
            );
        }

        $this->assertStructTreeRoot($state, $objectsById, $parentTreeRequired);
        $this->assertDocumentStructElem($state, $objectsById);
        $this->assertPageStructParents($state, $objectsById);
        $this->assertParentTree($state, $objectsById, $expectedParentTreeEntries);
        $this->assertLeafStructElements($state, $objectsById);
        $this->assertListStructElements($state, $objectsById);
        $this->assertTableStructElements($document, $state, $objectsById);
        $this->assertGenericContainerStructElements($state, $objectsById);
        $this->assertLinkStructElements($state, $objectsById);
        $this->assertPageAnnotationStructElements($state, $objectsById);
        $this->assertFormStructElements($state, $objectsById);
    }

    /**
     * @param list<IndirectObject> $objects
     * @return array<int, IndirectObject>
     */
    private function indexObjectsById(array $objects): array
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
    private function assertStructTreeRoot(
        DocumentSerializationPlanBuildState $state,
        array $objectsById,
        bool $parentTreeRequired,
    ): void {
        $contents = $this->requireObjectContents($objectsById, $state->structTreeRootObjectId, 'StructTreeRoot');

        if (!str_contains($contents, '/Type /StructTreeRoot')) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'PDF/A tagged StructTreeRoot object is missing /Type /StructTreeRoot.',
            );
        }

        $kidObjectIds = $this->extractReferenceArray($contents, '/K', 'StructTreeRoot');

        if ($kidObjectIds !== [$state->documentStructElemObjectId]) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged StructTreeRoot must reference exactly the document structure element %d 0 R.',
                $state->documentStructElemObjectId,
            ));
        }

        $parentTreeObjectId = $this->extractSingleReference($contents, '/ParentTree');

        if ($parentTreeRequired && $parentTreeObjectId !== $state->parentTreeObjectId) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged StructTreeRoot must reference ParentTree %d 0 R.',
                $state->parentTreeObjectId,
            ));
        }

        if (!$parentTreeRequired && $parentTreeObjectId !== null) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'PDF/A tagged StructTreeRoot must not reference an unused ParentTree.',
            );
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertDocumentStructElem(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        $contents = $this->requireObjectContents($objectsById, $state->documentStructElemObjectId, 'document StructElem');

        if (!str_contains($contents, '/Type /StructElem') || !str_contains($contents, '/S /Document')) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'PDF/A tagged document structure element must use /Type /StructElem and /S /Document.',
            );
        }

        $parentObjectId = $this->extractSingleReference($contents, '/P');

        if ($parentObjectId !== $state->structTreeRootObjectId) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged document structure element must reference StructTreeRoot %d 0 R as /P.',
                $state->structTreeRootObjectId,
            ));
        }

        $kidObjectIds = $this->extractReferenceArray($contents, '/K', 'document StructElem');
        $expectedKidObjectIds = $this->expectedDocumentChildObjectIds($state);

        if ($kidObjectIds !== $expectedKidObjectIds) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged document structure element must list child structure elements in reading order. Expected [%s], got [%s].',
                implode(', ', $expectedKidObjectIds),
                implode(', ', $kidObjectIds),
            ));
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertPageStructParents(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        foreach ($state->pageStructParentIds as $pageIndex => $structParentId) {
            $pageObjectId = $state->pageObjectIds[$pageIndex];
            $contents = $this->requireObjectContents($objectsById, $pageObjectId, sprintf('page %d object', $pageIndex + 1));

            if (!str_contains($contents, '/StructParents ' . $structParentId)) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged page %d must expose /StructParents %d for its marked content.',
                    $pageIndex + 1,
                    $structParentId,
                ));
            }
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     * @param array<int, list<int>> $expectedEntries
     */
    private function assertParentTree(
        DocumentSerializationPlanBuildState $state,
        array $objectsById,
        array $expectedEntries,
    ): void {
        if ($expectedEntries === []) {
            return;
        }

        $contents = $this->requireObjectContents($objectsById, $state->parentTreeObjectId, 'ParentTree');
        $entries = $this->extractParentTreeEntries($contents);

        if ($entries !== $expectedEntries) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged ParentTree entries must match the tagged content mapping. Expected [%s], got [%s].',
                $this->formatParentTreeEntries($expectedEntries),
                $this->formatParentTreeEntries($entries),
            ));
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertGenericContainerStructElements(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        foreach ($state->taggedStructure->containerEntries as $containerEntry) {
            $objectId = $state->taggedStructureObjectIds->genericStructElemObjectIds[$containerEntry['key']];
            $contents = $this->requireObjectContents(
                $objectsById,
                $objectId,
                sprintf('container StructElem "%s"', $containerEntry['key']),
            );
            $this->assertStructElemTagAndParent(
                $contents,
                $containerEntry['tag'],
                $this->expectedParentObjectId($containerEntry['key'], $state),
            );
            $this->assertKidReferences(
                $contents,
                array_map(
                    fn (string $childKey): int => $this->resolveDocumentChildObjectId($childKey, $state),
                    $containerEntry['childKeys'],
                ),
                sprintf('container StructElem "%s"', $containerEntry['key']),
            );
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertLeafStructElements(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        foreach ($state->taggedStructure->figureEntries as $figureEntry) {
            $contents = $this->requireObjectContents(
                $objectsById,
                $state->taggedStructureObjectIds->figureStructElemObjectIds[$figureEntry['key']],
                sprintf('figure StructElem "%s"', $figureEntry['key']),
            );
            $this->assertStructElemTagAndParent($contents, 'Figure', $this->expectedParentObjectId($figureEntry['key'], $state));
            $this->assertStructElemPageAndMarkedContent(
                $contents,
                $state->pageObjectIds[$figureEntry['pageIndex']],
                $figureEntry['markedContentId'],
            );

            if ($figureEntry['altText'] !== null && !str_contains($contents, '/Alt ')) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged figure StructElem "%s" must expose /Alt text.',
                    $figureEntry['key'],
                ));
            }
        }

        foreach ($state->taggedStructure->textEntries as $textEntry) {
            $contents = $this->requireObjectContents(
                $objectsById,
                $state->taggedStructureObjectIds->textStructElemObjectIds[$textEntry['key']],
                sprintf('text StructElem "%s"', $textEntry['key']),
            );
            $this->assertStructElemTagAndParent($contents, $textEntry['tag'], $this->expectedParentObjectId($textEntry['key'], $state));

            if (count($textEntry['references']) === 1) {
                $this->assertStructElemPageAndMarkedContent(
                    $contents,
                    $state->pageObjectIds[$textEntry['references'][0]['pageIndex']],
                    $textEntry['references'][0]['markedContentId'],
                );

                continue;
            }

            $this->assertMcrKids(
                $contents,
                array_map(
                    fn (array $reference): array => [
                        $state->pageObjectIds[$reference['pageIndex']],
                        $reference['markedContentId'],
                    ],
                    $textEntry['references'],
                ),
                sprintf('text StructElem "%s"', $textEntry['key']),
            );
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertListStructElements(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        foreach ($state->taggedStructure->listEntries as $listEntry) {
            $listObjectId = $state->taggedStructureObjectIds->listStructElemObjectIds[$listEntry['key']];
            $listContents = $this->requireObjectContents($objectsById, $listObjectId, sprintf('list StructElem "%s"', $listEntry['key']));
            $this->assertStructElemTagAndParent($listContents, 'L', $this->expectedParentObjectId($listEntry['key'], $state));

            $expectedListKids = [];

            foreach ($listEntry['itemEntries'] as $itemEntry) {
                $expectedListKids[] = $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']];
            }

            $this->assertKidReferences($listContents, $expectedListKids, sprintf('list StructElem "%s"', $listEntry['key']));

            foreach ($listEntry['itemEntries'] as $itemEntry) {
                $itemObjectId = $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']];
                $itemContents = $this->requireObjectContents($objectsById, $itemObjectId, sprintf('list item StructElem "%s"', $itemEntry['key']));
                $this->assertStructElemTagAndParent($itemContents, 'LI', $listObjectId);
                $this->assertKidReferences(
                    $itemContents,
                    [
                        $state->taggedStructureObjectIds->listLabelStructElemObjectIds[$itemEntry['labelKey']],
                        $state->taggedStructureObjectIds->listBodyStructElemObjectIds[$itemEntry['bodyKey']],
                    ],
                    sprintf('list item StructElem "%s"', $itemEntry['key']),
                );

                $labelContents = $this->requireObjectContents(
                    $objectsById,
                    $state->taggedStructureObjectIds->listLabelStructElemObjectIds[$itemEntry['labelKey']],
                    sprintf('list label StructElem "%s"', $itemEntry['labelKey']),
                );
                $this->assertStructElemTagAndParent($labelContents, 'Lbl', $itemObjectId);
                $this->assertMcrKids(
                    $labelContents,
                    [[$state->pageObjectIds[$itemEntry['labelReference']->pageIndex], $itemEntry['labelReference']->markedContentId]],
                    sprintf('list label StructElem "%s"', $itemEntry['labelKey']),
                );

                $bodyContents = $this->requireObjectContents(
                    $objectsById,
                    $state->taggedStructureObjectIds->listBodyStructElemObjectIds[$itemEntry['bodyKey']],
                    sprintf('list body StructElem "%s"', $itemEntry['bodyKey']),
                );
                $this->assertStructElemTagAndParent($bodyContents, 'LBody', $itemObjectId);
                $this->assertMcrKids(
                    $bodyContents,
                    [[$state->pageObjectIds[$itemEntry['bodyReference']->pageIndex], $itemEntry['bodyReference']->markedContentId]],
                    sprintf('list body StructElem "%s"', $itemEntry['bodyKey']),
                );
            }
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertTableStructElements(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        array $objectsById,
    ): void {
        foreach ($document->taggedTables as $taggedTable) {
            $tableKey = $taggedTable->key ?? TaggedStructureObjectIds::tableKey($taggedTable->tableId);
            $tableObjectId = $state->taggedStructureObjectIds->tableStructElemObjectIds[$tableKey];
            $tableContents = $this->requireObjectContents($objectsById, $tableObjectId, sprintf('table StructElem "%s"', $tableKey));
            $this->assertStructElemTagAndParent($tableContents, 'Table', $this->expectedParentObjectId($tableKey, $state));

            $expectedTableKids = [];

            if ($taggedTable->hasCaption()) {
                $expectedTableKids[] = $state->taggedStructureObjectIds->captionStructElemObjectIds[
                    TaggedStructureObjectIds::tableCaptionKey($taggedTable->tableId)
                ];
            }

            foreach ($this->taggedTableSections($document, $taggedTable) as $section => $rows) {
                if ($rows !== []) {
                    $expectedTableKids[] = $state->taggedStructureObjectIds->tableSectionStructElemObjectIds[
                        TaggedStructureObjectIds::tableSectionKey($taggedTable->tableId, $section)
                    ];
                }
            }

            $this->assertKidReferences($tableContents, $expectedTableKids, sprintf('table StructElem "%s"', $tableKey));

            if ($taggedTable->hasCaption()) {
                $captionKey = TaggedStructureObjectIds::tableCaptionKey($taggedTable->tableId);
                $captionContents = $this->requireObjectContents(
                    $objectsById,
                    $state->taggedStructureObjectIds->captionStructElemObjectIds[$captionKey],
                    sprintf('table caption StructElem "%s"', $captionKey),
                );
                $this->assertStructElemTagAndParent($captionContents, 'Caption', $tableObjectId);
                $expectedCaptionKids = [];

                foreach ($taggedTable->captionReferences as $reference) {
                    $expectedCaptionKids[] = [$state->pageObjectIds[$reference->pageIndex], $reference->markedContentId];
                }

                $this->assertMcrKids($captionContents, $expectedCaptionKids, sprintf('table caption StructElem "%s"', $captionKey));
            }

            foreach ($this->taggedTableSections($document, $taggedTable) as $section => $rows) {
                if ($rows === []) {
                    continue;
                }

                $sectionKey = TaggedStructureObjectIds::tableSectionKey($taggedTable->tableId, $section);
                $sectionObjectId = $state->taggedStructureObjectIds->tableSectionStructElemObjectIds[$sectionKey];
                $sectionContents = $this->requireObjectContents($objectsById, $sectionObjectId, sprintf('table section StructElem "%s"', $sectionKey));
                $this->assertStructElemTagAndParent(
                    $sectionContents,
                    $document->profile->isPdfA1() ? 'Sect' : match ($section) {
                        'header' => 'THead',
                        'footer' => 'TFoot',
                        default => 'TBody',
                    },
                    $tableObjectId,
                );

                $expectedSectionKids = [];

                foreach ($rows as $row) {
                    $expectedSectionKids[] = $state->taggedStructureObjectIds->rowStructElemObjectIds[
                        TaggedStructureObjectIds::tableRowKey($taggedTable->tableId, $section, $row->rowIndex)
                    ];
                }

                $this->assertKidReferences($sectionContents, $expectedSectionKids, sprintf('table section StructElem "%s"', $sectionKey));

                foreach ($rows as $row) {
                    $rowKey = TaggedStructureObjectIds::tableRowKey($taggedTable->tableId, $section, $row->rowIndex);
                    $rowObjectId = $state->taggedStructureObjectIds->rowStructElemObjectIds[$rowKey];
                    $rowContents = $this->requireObjectContents($objectsById, $rowObjectId, sprintf('table row StructElem "%s"', $rowKey));
                    $this->assertStructElemTagAndParent($rowContents, 'TR', $sectionObjectId);

                    $expectedRowKids = [];

                    foreach ($row->cells as $cell) {
                        $expectedRowKids[] = $state->taggedStructureObjectIds->cellStructElemObjectIds[
                            TaggedStructureObjectIds::tableCellKey($taggedTable->tableId, $section, $row->rowIndex, $cell->columnIndex)
                        ];
                    }

                    $this->assertKidReferences($rowContents, $expectedRowKids, sprintf('table row StructElem "%s"', $rowKey));

                    foreach ($row->cells as $cell) {
                        $cellKey = TaggedStructureObjectIds::tableCellKey($taggedTable->tableId, $section, $row->rowIndex, $cell->columnIndex);
                        $cellContents = $this->requireObjectContents(
                            $objectsById,
                            $state->taggedStructureObjectIds->cellStructElemObjectIds[$cellKey],
                            sprintf('table cell StructElem "%s"', $cellKey),
                        );
                        $this->assertStructElemTagAndParent($cellContents, $cell->header ? 'TH' : 'TD', $rowObjectId);
                        $expectedCellKids = [];

                        foreach ($cell->contentReferences as $reference) {
                            $expectedCellKids[] = [$state->pageObjectIds[$reference->pageIndex], $reference->markedContentId];
                        }

                        $this->assertMcrKids($cellContents, $expectedCellKids, sprintf('table cell StructElem "%s"', $cellKey));
                    }
                }
            }
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertLinkStructElements(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        foreach ($state->taggedLinkStructure['linkEntries'] as $linkEntry) {
            $objectId = $state->taggedStructureObjectIds->linkStructElemObjectIds[$linkEntry['key']];
            $contents = $this->requireObjectContents($objectsById, $objectId, sprintf('link StructElem "%s"', $linkEntry['key']));

            $this->assertStructElemTagAndParent($contents, 'Link', $this->expectedParentObjectId($linkEntry['key'], $state));

            $pageObjectId = $state->pageObjectIds[$linkEntry['pageIndex']];
            $actualPageObjectId = $this->extractSingleReference($contents, '/Pg');

            if ($actualPageObjectId !== $pageObjectId) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged link StructElem "%s" must reference page object %d 0 R.',
                    $linkEntry['key'],
                    $pageObjectId,
                ));
            }

            if (!str_contains($contents, '/Alt ')) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged link StructElem "%s" must expose /Alt text.',
                    $linkEntry['key'],
                ));
            }

            $kidSection = $this->extractKidSection($contents, sprintf('link StructElem "%s"', $linkEntry['key']));
            $actualMarkedContentIds = $this->extractLinkMarkedContentIds($kidSection);

            if ($actualMarkedContentIds !== $linkEntry['markedContentIds']) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged link StructElem "%s" must list the expected MCID kids. Expected [%s], got [%s].',
                    $linkEntry['key'],
                    implode(', ', $linkEntry['markedContentIds']),
                    implode(', ', $actualMarkedContentIds),
                ));
            }

            $expectedAnnotationObjectIds = [];

            foreach ($linkEntry['annotationIndices'] as $annotationIndex) {
                $expectedAnnotationObjectIds[] = $state->pageAnnotationObjectIds[$linkEntry['pageIndex']][$annotationIndex];
            }

            $actualObjrObjectIds = $this->extractObjrObjectIds($kidSection);

            if ($actualObjrObjectIds !== $expectedAnnotationObjectIds) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged link StructElem "%s" must reference the expected annotation objects. Expected [%s], got [%s].',
                    $linkEntry['key'],
                    implode(', ', $expectedAnnotationObjectIds),
                    implode(', ', $actualObjrObjectIds),
                ));
            }
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertPageAnnotationStructElements(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        foreach ($state->taggedPageAnnotationStructure['entries'] as $annotationEntry) {
            $objectId = $state->taggedStructureObjectIds->annotationStructElemObjectIds[$annotationEntry['key']];
            $contents = $this->requireObjectContents($objectsById, $objectId, sprintf('page annotation StructElem "%s"', $annotationEntry['key']));
            $this->assertStructElemTagAndParent($contents, $annotationEntry['tag'], $state->documentStructElemObjectId);

            $pageObjectId = $state->pageObjectIds[$annotationEntry['pageIndex']];
            $actualPageObjectId = $this->extractSingleReference($contents, '/Pg');

            if ($actualPageObjectId !== $pageObjectId) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged page annotation StructElem "%s" must reference page object %d 0 R.',
                    $annotationEntry['key'],
                    $pageObjectId,
                ));
            }

            if (!str_contains($contents, '/Alt ')) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged page annotation StructElem "%s" must expose /Alt text.',
                    $annotationEntry['key'],
                ));
            }

            $kidSection = $this->extractKidSection($contents, sprintf('page annotation StructElem "%s"', $annotationEntry['key']));
            $actualObjrObjectIds = $this->extractObjrObjectIds($kidSection);
            $expectedObjectId = $state->pageAnnotationObjectIds[$annotationEntry['pageIndex']][$annotationEntry['annotationIndex']];

            if ($actualObjrObjectIds !== [$expectedObjectId]) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged page annotation StructElem "%s" must reference annotation object %d 0 R.',
                    $annotationEntry['key'],
                    $expectedObjectId,
                ));
            }
        }
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function assertFormStructElements(DocumentSerializationPlanBuildState $state, array $objectsById): void
    {
        foreach ($state->taggedFormStructure['entries'] as $formEntry) {
            $objectId = $state->taggedFormStructElemObjectIds[$formEntry['key']];
            $contents = $this->requireObjectContents($objectsById, $objectId, sprintf('form StructElem "%s"', $formEntry['key']));
            $this->assertStructElemTagAndParent($contents, 'Form', $state->documentStructElemObjectId);

            $pageObjectId = $state->pageObjectIds[$formEntry['pageIndex']];
            $actualPageObjectId = $this->extractSingleReference($contents, '/Pg');

            if ($actualPageObjectId !== $pageObjectId) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged form StructElem "%s" must reference page object %d 0 R.',
                    $formEntry['key'],
                    $pageObjectId,
                ));
            }

            if (!str_contains($contents, '/Alt ')) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged form StructElem "%s" must expose /Alt text.',
                    $formEntry['key'],
                ));
            }

            $kidSection = $this->extractKidSection($contents, sprintf('form StructElem "%s"', $formEntry['key']));
            $actualObjrObjectIds = $this->extractObjrObjectIds($kidSection);

            if ($actualObjrObjectIds !== [$formEntry['annotationObjectId']]) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                    'PDF/A tagged form StructElem "%s" must reference widget annotation object %d 0 R.',
                    $formEntry['key'],
                    $formEntry['annotationObjectId'],
                ));
            }
        }
    }

    /**
     * @return array<int, list<int>>
     */
    private function expectedParentTreeEntries(DocumentSerializationPlanBuildState $state): array
    {
        $entries = [];

        foreach ($state->pageStructParentIds as $pageIndex => $structParentId) {
            $pageKeys = $state->taggedStructure->pageMarkedContentKeys[$pageIndex] ?? [];

            if ($pageKeys === []) {
                continue;
            }

            ksort($pageKeys);
            $entries[$structParentId] = [];

            foreach ($pageKeys as $key) {
                $entries[$structParentId][] = $state->taggedStructureObjectIds->resolvePageContentObjectId($key);
            }
        }

        foreach ($state->taggedLinkStructure['parentTreeEntries'] as $structParentId => $linkKeys) {
            $entries[$structParentId] = [];

            foreach ($linkKeys as $key) {
                $entries[$structParentId][] = $state->taggedStructureObjectIds->linkStructElemObjectIds[$key];
            }
        }

        foreach ($state->taggedPageAnnotationStructure['parentTreeEntries'] as $structParentId => $annotationKeys) {
            $entries[$structParentId] = [];

            foreach ($annotationKeys as $key) {
                $entries[$structParentId][] = $state->taggedStructureObjectIds->annotationStructElemObjectIds[$key];
            }
        }

        foreach ($state->taggedFormStructure['parentTreeEntries'] as $structParentId => $formKeys) {
            $entries[$structParentId] = [];

            foreach ($formKeys as $key) {
                $entries[$structParentId][] = $state->taggedFormStructElemObjectIds[$key];
            }
        }

        ksort($entries);

        return $entries;
    }

    /**
     * @return list<int>
     */
    private function expectedDocumentChildObjectIds(DocumentSerializationPlanBuildState $state): array
    {
        return array_map(
            fn (string $key): int => $this->resolveDocumentChildObjectId($key, $state),
            $this->taggedStructureLayoutPolicy->orderedDocumentChildKeys($state),
        );
    }

    private function resolveDocumentChildObjectId(string $key, DocumentSerializationPlanBuildState $state): int
    {
        return $state->taggedFormStructElemObjectIds[$key] ?? $state->taggedStructureObjectIds->resolveStructElemObjectId($key);
    }

    private function expectedParentObjectId(string $key, DocumentSerializationPlanBuildState $state): int
    {
        $parentKey = $this->taggedStructureLayoutPolicy->explicitParentKey($key, $state);

        if ($parentKey === null) {
            return $state->documentStructElemObjectId
                ?? throw new DocumentValidationException(
                    DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                    'PDF/A tagged document structure root object is missing.',
                );
        }

        return $state->taggedStructureObjectIds->genericStructElemObjectIds[$parentKey]
            ?? throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                sprintf('Unknown PDF/A tagged parent structure key "%s".', $parentKey),
            );
    }

    /**
     * @param array<int, IndirectObject> $objectsById
     */
    private function requireObjectContents(array $objectsById, ?int $objectId, string $label): string
    {
        if ($objectId === null || !array_key_exists($objectId, $objectsById)) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                sprintf('PDF/A tagged %s object is missing.', $label),
            );
        }

        return $objectsById[$objectId]->contents;
    }

    private function assertStructElemTagAndParent(string $contents, string $tag, ?int $parentObjectId): void
    {
        if ($parentObjectId === null) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged structure element /S /%s requires a parent object id.',
                $tag,
            ));
        }

        if (!str_contains($contents, '/Type /StructElem') || !str_contains($contents, '/S /' . $tag)) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged structure element must use /Type /StructElem and /S /%s.',
                $tag,
            ));
        }

        $actualParentObjectId = $this->extractSingleReference($contents, '/P');

        if ($actualParentObjectId !== $parentObjectId) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged structure element /S /%s must reference parent object %d 0 R.',
                $tag,
                $parentObjectId,
            ));
        }
    }

    private function assertStructElemPageAndMarkedContent(string $contents, int $pageObjectId, int $markedContentId): void
    {
        $actualPageObjectId = $this->extractSingleReference($contents, '/Pg');

        if ($actualPageObjectId !== $pageObjectId) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged structure element must reference page object %d 0 R.',
                $pageObjectId,
            ));
        }

        if (!preg_match('/\/K\s+' . $markedContentId . '(?=\D|$)/', $contents)) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged structure element must reference MCID %d as /K.',
                $markedContentId,
            ));
        }
    }

    /**
     * @param list<int> $expectedObjectIds
     */
    private function assertKidReferences(string $contents, array $expectedObjectIds, string $label): void
    {
        $actualObjectIds = $this->extractReferenceArray($contents, '/K', $label);

        if ($actualObjectIds !== $expectedObjectIds) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged %s must list the expected child structure elements. Expected [%s], got [%s].',
                $label,
                implode(', ', $expectedObjectIds),
                implode(', ', $actualObjectIds),
            ));
        }
    }

    /**
     * @param list<array{0: int, 1: int}> $expectedEntries
     */
    private function assertMcrKids(string $contents, array $expectedEntries, string $label): void
    {
        $kidSection = $this->extractKidSection($contents, $label);
        $matches = [];
        preg_match_all('/<<\s*\/Type\s*\/MCR\s*\/Pg\s*(\d+)\s+0\s+R\s*\/MCID\s*(\d+)\s*>>/', $kidSection, $matches, PREG_SET_ORDER);
        $actualEntries = [];

        foreach ($matches as $match) {
            $actualEntries[] = [(int) $match[1], (int) $match[2]];
        }

        if ($actualEntries !== $expectedEntries) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID, sprintf(
                'PDF/A tagged %s must expose the expected MCR kids.',
                $label,
            ));
        }
    }

    /**
     * @return list<int>
     */
    private function extractReferenceArray(string $contents, string $entry, string $label): array
    {
        if (!preg_match('/' . preg_quote($entry, '/') . '\s*\[([^\]]*)\]/', $contents, $matches)) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                sprintf('PDF/A tagged %s is missing %s array.', $label, $entry),
            );
        }

        $references = [];
        preg_match_all('/(\d+)\s+0\s+R/', $matches[1], $referenceMatches);

        foreach ($referenceMatches[1] as $objectId) {
            $references[] = (int) $objectId;
        }

        return $references;
    }

    private function extractKidSection(string $contents, string $label): string
    {
        if (!preg_match('/\/K\s*\[(.*)\](?!.*\/K\s*\[)/', $contents, $matches)) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                sprintf('PDF/A tagged %s is missing /K kids.', $label),
            );
        }

        return $matches[1];
    }

    private function extractSingleReference(string $contents, string $entry): ?int
    {
        if (!preg_match('/' . preg_quote($entry, '/') . '\s+(\d+)\s+0\s+R/', $contents, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return array<int, list<int>>
     */
    private function extractParentTreeEntries(string $contents): array
    {
        if (!preg_match('/\/Nums\s*\[(.*)\]/', $contents, $matches)) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'PDF/A tagged ParentTree is missing a /Nums array.',
            );
        }

        $entries = [];
        preg_match_all('/(\d+)\s*\[((?:\d+\s+0\s+R\s*)*)\]/', $matches[1], $entryMatches, PREG_SET_ORDER);

        foreach ($entryMatches as $entryMatch) {
            $objectIds = [];
            preg_match_all('/(\d+)\s+0\s+R/', $entryMatch[2], $objectIdMatches);

            foreach ($objectIdMatches[1] as $objectId) {
                $objectIds[] = (int) $objectId;
            }

            $entries[(int) $entryMatch[1]] = $objectIds;
        }

        ksort($entries);

        return $entries;
    }

    /**
     * @param array<int, list<int>> $entries
     */
    private function formatParentTreeEntries(array $entries): string
    {
        $parts = [];

        foreach ($entries as $structParentId => $objectIds) {
            $parts[] = $structParentId . ':[' . implode(', ', $objectIds) . ']';
        }

        return implode('; ', $parts);
    }

    /**
     * @return list<int>
     */
    private function extractLinkMarkedContentIds(string $kidSection): array
    {
        $normalizedKidSection = preg_replace('/<<.*?>>/s', ' ', $kidSection) ?? $kidSection;
        $matches = [];
        preg_match_all('/(?<!\d)(\d+)(?!\s+0\s+R)(?!\d)/', $normalizedKidSection, $matches);

        return array_map(
            static fn (string $value): int => (int) $value,
            $matches[1],
        );
    }

    /**
     * @return list<int>
     */
    private function extractObjrObjectIds(string $kidSection): array
    {
        $matches = [];
        preg_match_all('/<<\s*\/Type\s*\/OBJR\s*\/Obj\s*(\d+)\s+0\s+R\s*\/Pg\s*\d+\s+0\s+R\s*>>/', $kidSection, $matches);

        return array_map(
            static fn (string $value): int => (int) $value,
            $matches[1],
        );
    }

    /**
     * @return array{header: list<TaggedTableRow>, body: list<TaggedTableRow>, footer: list<TaggedTableRow>}
     */
    private function taggedTableSections(Document $document, TaggedTable $taggedTable): array
    {
        return [
            'header' => $taggedTable->headerRows,
            'body' => $taggedTable->bodyRows,
            'footer' => $taggedTable->footerRows,
        ];
    }
}
