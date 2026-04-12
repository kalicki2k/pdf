<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_map;
use function array_values;
use function count;

use InvalidArgumentException;
use Kalle\Pdf\Document\Form\WidgetFormField;
use Kalle\Pdf\Document\TaggedPdf\ParentTree;
use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Document\TaggedPdf\StructTreeRoot;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureObjectIds;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Writer\IndirectObject;

use function preg_match;
use function sprintf;
use function usort;

final class DocumentTaggedPdfObjectBuilder
{
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
        if (!$document->profile->requiresTaggedLinkAnnotations()) {
            return [
                'linkEntries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
                'nextStructParentId' => $nextStructParentId,
            ];
        }

        /** @var array<string, array{
         *   key: string,
         *   pageIndex: int,
         *   annotationIndices: list<int>,
         *   altTextParts: list<string>,
         *   markedContentIds: list<int>
         * }> $groupedLinkEntries
         */
        $groupedLinkEntries = [];
        $parentTreeEntries = [];
        $structParentIds = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                if (!$annotation instanceof LinkAnnotation) {
                    continue;
                }

                $annotationKey = $pageIndex . ':' . $annotationIndex;
                $groupKey = $annotation->taggedGroupKey ?? $annotationKey;

                if (!isset($groupedLinkEntries[$groupKey])) {
                    $groupedLinkEntries[$groupKey] = [
                        'key' => $groupKey,
                        'pageIndex' => $pageIndex,
                        'annotationIndices' => [],
                        'altTextParts' => [],
                        'markedContentIds' => [],
                    ];
                }

                $groupedLinkEntries[$groupKey]['annotationIndices'][] = $annotationIndex;

                $accessibleLabel = $annotation->accessibleLabelOrContents();

                if ($accessibleLabel !== null && $accessibleLabel !== '') {
                    $lastAltTextPart = $groupedLinkEntries[$groupKey]['altTextParts'] === []
                        ? null
                        : $groupedLinkEntries[$groupKey]['altTextParts'][array_key_last($groupedLinkEntries[$groupKey]['altTextParts'])];

                    if ($lastAltTextPart !== $accessibleLabel) {
                        $groupedLinkEntries[$groupKey]['altTextParts'][] = $accessibleLabel;
                    }
                }

                if ($annotation->markedContentId() !== null) {
                    $groupedLinkEntries[$groupKey]['markedContentIds'][] = $annotation->markedContentId();
                }

                $structParentIds[$annotationKey] = $nextStructParentId;
                $parentTreeEntries[$nextStructParentId] = [$groupKey];
                $nextStructParentId++;
            }
        }

        $linkEntries = array_map(
            fn (array $entry): array => [
                'key' => $entry['key'],
                'pageIndex' => $entry['pageIndex'],
                'annotationIndices' => $entry['annotationIndices'],
                'altText' => $this->joinTaggedLinkAltText($entry['altTextParts']),
                'markedContentIds' => $entry['markedContentIds'],
            ],
            array_values($groupedLinkEntries),
        );

        return [
            'linkEntries' => $linkEntries,
            'parentTreeEntries' => $parentTreeEntries,
            'structParentIds' => $structParentIds,
            'nextStructParentId' => $nextStructParentId,
        ];
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
        if (!$document->profile->requiresTaggedFormFields() || $document->acroForm === null) {
            return [
                'entries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
            ];
        }

        $entries = [];
        $parentTreeEntries = [];
        $structParentIds = [];

        foreach ($document->acroForm->fields as $fieldIndex => $field) {
            if (!$field instanceof WidgetFormField) {
                continue;
            }

            $annotationObjectIdsByPage = $field->pageAnnotationObjectIds(
                $acroFormFieldObjectIds[$fieldIndex],
                $acroFormFieldRelatedObjectIds[$fieldIndex] ?? [],
            );
            $annotationObjectIds = [];

            foreach ($annotationObjectIdsByPage as $pageAnnotationObjectIds) {
                $annotationObjectIds = [...$annotationObjectIds, ...$pageAnnotationObjectIds];
            }

            if (count($annotationObjectIds) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'Tagged PDF/UA form support currently requires exactly one widget annotation for field "%s".',
                    $field->name,
                ));
            }

            $entryKey = 'form:' . $field->name;
            $annotationObjectId = $annotationObjectIds[0];
            $entries[] = [
                'key' => $entryKey,
                'pageIndex' => $field->pageNumber - 1,
                'annotationObjectId' => $annotationObjectId,
                'altText' => $field->alternativeName ?? $field->name,
            ];
            $structParentIds[$annotationObjectId] = $nextStructParentId;
            $parentTreeEntries[$nextStructParentId] = [$entryKey];
            $nextStructParentId++;
        }

        return [
            'entries' => $entries,
            'parentTreeEntries' => $parentTreeEntries,
            'structParentIds' => $structParentIds,
        ];
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
        $documentKidObjectIds = array_map(
            fn (array $entry): int => $this->resolveDocumentKidObjectId($entry['key'], $state),
            $this->documentChildEntriesInReadingOrder($state),
        );

        $objects[] = new IndirectObject(
            $state->structTreeRootObjectId,
            (new StructTreeRoot([$state->documentStructElemObjectId], $state->parentTreeObjectId))->objectContents(),
        );
        $objects[] = new IndirectObject(
            $state->documentStructElemObjectId,
            (new StructElem('Document', $state->structTreeRootObjectId, $documentKidObjectIds))->objectContents(),
        );

        if ($state->parentTreeObjectId !== null) {
            $parentTreeEntries = [];

            foreach ($state->pageStructParentIds as $pageIndex => $structParentId) {
                $pageKeys = $state->taggedStructure->pageMarkedContentKeys[$pageIndex] ?? [];

                if ($pageKeys === []) {
                    continue;
                }

                ksort($pageKeys);
                $parentTreeEntries[$structParentId] = array_map(
                    fn (string $key): int => $state->taggedStructureObjectIds->resolvePageContentObjectId($key),
                    array_values($pageKeys),
                );
            }

            foreach ($state->taggedLinkStructure['parentTreeEntries'] as $structParentId => $linkKeys) {
                $parentTreeEntries[$structParentId] = array_map(
                    fn (string $key): int => $state->taggedStructureObjectIds->linkStructElemObjectIds[$key],
                    $linkKeys,
                );
            }

            foreach ($state->taggedFormStructure['parentTreeEntries'] as $structParentId => $formKeys) {
                $parentTreeEntries[$structParentId] = array_map(
                    fn (string $key): int => $state->taggedFormStructElemObjectIds[$key],
                    $formKeys,
                );
            }

            $objects[] = new IndirectObject($state->parentTreeObjectId, (new ParentTree($parentTreeEntries))->objectContents());
        }

        foreach ($state->taggedStructure->figureEntries as $figureEntry) {
            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->figureStructElemObjectIds[$figureEntry['key']],
                (new StructElem(
                    'Figure',
                    $state->documentStructElemObjectId,
                    pageObjectId: $state->pageObjectIds[$figureEntry['pageIndex']],
                    altText: $figureEntry['altText'],
                    markedContentId: $figureEntry['markedContentId'],
                ))->objectContents(),
            );
        }

        foreach ($state->taggedStructure->textEntries as $textEntry) {
            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->textStructElemObjectIds[$textEntry['key']],
                (new StructElem(
                    $textEntry['tag'],
                    $state->documentStructElemObjectId,
                    pageObjectId: $state->pageObjectIds[$textEntry['pageIndex']],
                    markedContentId: $textEntry['markedContentId'],
                ))->objectContents(),
            );
        }

        foreach ($state->taggedStructure->listEntries as $listEntry) {
            $listKidObjectIds = [];

            foreach ($listEntry['itemEntries'] as $itemEntry) {
                $listKidObjectIds[] = $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']];
            }

            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->listStructElemObjectIds[$listEntry['key']],
                (new StructElem('L', $state->documentStructElemObjectId, $listKidObjectIds))->objectContents(),
            );

            foreach ($listEntry['itemEntries'] as $itemEntry) {
                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']],
                    (new StructElem(
                        'LI',
                        $state->taggedStructureObjectIds->listStructElemObjectIds[$listEntry['key']],
                        [
                            $state->taggedStructureObjectIds->listLabelStructElemObjectIds[$itemEntry['labelKey']],
                            $state->taggedStructureObjectIds->listBodyStructElemObjectIds[$itemEntry['bodyKey']],
                        ],
                    ))->objectContents(),
                );
                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->listLabelStructElemObjectIds[$itemEntry['labelKey']],
                    (new StructElem(
                        'Lbl',
                        $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']],
                        kidEntries: $this->taggedMarkedContentKidEntries([$itemEntry['labelReference']], $state->pageObjectIds),
                    ))->objectContents(),
                );
                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->listBodyStructElemObjectIds[$itemEntry['bodyKey']],
                    (new StructElem(
                        'LBody',
                        $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']],
                        kidEntries: $this->taggedMarkedContentKidEntries([$itemEntry['bodyReference']], $state->pageObjectIds),
                    ))->objectContents(),
                );
            }
        }

        foreach ($document->taggedTables as $taggedTable) {
            $tableStructKey = TaggedStructureObjectIds::tableKey($taggedTable->tableId);
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
                (new StructElem('Table', $state->documentStructElemObjectId, $tableKidObjectIds))->objectContents(),
            );

            if ($taggedTable->hasCaption()) {
                $captionKey = TaggedStructureObjectIds::tableCaptionKey($taggedTable->tableId);
                $objects[] = new IndirectObject(
                    $state->taggedStructureObjectIds->captionStructElemObjectIds[$captionKey],
                    (new StructElem(
                        'Caption',
                        $state->taggedStructureObjectIds->tableStructElemObjectIds[$tableStructKey],
                        kidEntries: $this->taggedMarkedContentKidEntries($taggedTable->captionReferences, $state->pageObjectIds),
                    ))->objectContents(),
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
                    (new StructElem(
                        $this->taggedTableSectionTag($document, $section),
                        $state->taggedStructureObjectIds->tableStructElemObjectIds[$tableStructKey],
                        $sectionKidObjectIds,
                    ))->objectContents(),
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
                        (new StructElem('TR', $state->taggedStructureObjectIds->tableSectionStructElemObjectIds[$sectionKey], $rowKidObjectIds))->objectContents(),
                    );

                    foreach ($row->cells as $cell) {
                        $cellKey = TaggedStructureObjectIds::tableCellKey($taggedTable->tableId, $section, $row->rowIndex, $cell->columnIndex);
                        $objects[] = new IndirectObject(
                            $state->taggedStructureObjectIds->cellStructElemObjectIds[$cellKey],
                            (new StructElem(
                                $cell->header ? 'TH' : 'TD',
                                $state->taggedStructureObjectIds->rowStructElemObjectIds[$rowKey],
                                kidEntries: $this->taggedMarkedContentKidEntries($cell->contentReferences, $state->pageObjectIds),
                                scope: $cell->headerScope?->value,
                                rowSpan: $cell->rowspan > 1 ? $cell->rowspan : null,
                                colSpan: $cell->colspan > 1 ? $cell->colspan : null,
                            ))->objectContents(),
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
                (new StructElem(
                    'Link',
                    $state->documentStructElemObjectId,
                    pageObjectId: $pageObjectId,
                    altText: $linkEntry['altText'],
                    kidEntries: $kidEntries,
                ))->objectContents(),
            );
        }

        foreach ($state->taggedFormStructure['entries'] as $formEntry) {
            $pageObjectId = $state->pageObjectIds[$formEntry['pageIndex']];

            $objects[] = IndirectObject::plain(
                $state->taggedFormStructElemObjectIds[$formEntry['key']],
                (new StructElem(
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
                ))->objectContents(),
            );
        }

        return $objects;
    }

    /**
     * @return list<array{key: string, pageIndex: int, orderIndex: int, sequence: int}>
     */
    private function documentChildEntriesInReadingOrder(DocumentSerializationPlanBuildState $state): array
    {
        $entries = [];
        $sequence = 0;

        foreach ($state->taggedStructure->documentChildEntries as $entry) {
            $entries[] = [
                'key' => $entry['key'],
                'pageIndex' => $entry['pageIndex'],
                'orderIndex' => $entry['markedContentId'],
                'sequence' => $sequence++,
            ];
        }

        foreach ($state->taggedLinkStructure['linkEntries'] as $linkEntry) {
            $entries[] = [
                'key' => $linkEntry['key'],
                'pageIndex' => $linkEntry['pageIndex'],
                'orderIndex' => $linkEntry['markedContentIds'] !== []
                    ? min($linkEntry['markedContentIds'])
                    : 1000000 + ($linkEntry['annotationIndices'][0] ?? 0),
                'sequence' => $sequence++,
            ];
        }

        foreach ($state->taggedFormStructure['entries'] as $formEntry) {
            $entries[] = [
                'key' => $formEntry['key'],
                'pageIndex' => $formEntry['pageIndex'],
                'orderIndex' => 2000000,
                'sequence' => $sequence++,
            ];
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => [$left['pageIndex'], $left['orderIndex'], $left['sequence']]
                <=> [$right['pageIndex'], $right['orderIndex'], $right['sequence']],
        );

        return $entries;
    }

    private function resolveDocumentKidObjectId(string $key, DocumentSerializationPlanBuildState $state): int
    {
        return $state->taggedStructureObjectIds->figureStructElemObjectIds[$key]
            ?? $state->taggedStructureObjectIds->textStructElemObjectIds[$key]
            ?? $state->taggedStructureObjectIds->listStructElemObjectIds[$key]
            ?? $state->taggedStructureObjectIds->tableStructElemObjectIds[$key]
            ?? $state->taggedStructureObjectIds->linkStructElemObjectIds[$key]
            ?? $state->taggedFormStructElemObjectIds[$key]
            ?? throw new InvalidArgumentException(sprintf('Unknown tagged document child key "%s".', $key));
    }

    /**
     * @param list<string> $parts
     */
    private function joinTaggedLinkAltText(array $parts): string
    {
        $altText = '';

        foreach ($parts as $part) {
            if ($altText !== '' && $this->shouldInsertWhitespaceBetweenLinkAltTextParts($altText, $part)) {
                $altText .= ' ';
            }

            $altText .= $part;
        }

        return $altText;
    }

    private function shouldInsertWhitespaceBetweenLinkAltTextParts(string $left, string $right): bool
    {
        return preg_match('/[\pL\pN]$/u', $left) === 1
            && preg_match('/^[\pL\pN]/u', $right) === 1;
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
