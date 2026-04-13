<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_map;
use function array_values;
use function count;
use function preg_match;
use function sprintf;
use function usort;

use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\WidgetFormField;
use Kalle\Pdf\Document\TaggedPdf\ParentTree;
use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Document\TaggedPdf\StructTreeRoot;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureObjectIds;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PdfUaTaggedPageAnnotation;
use Kalle\Pdf\Writer\IndirectObject;

final readonly class DocumentTaggedPdfObjectBuilder
{
    public function __construct(
        private PdfA1aPageAnnotationPolicy $pdfA1aPageAnnotationPolicy = new PdfA1aPageAnnotationPolicy(),
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
                // Link groups stay page-local in the current model because the
                // StructElem uses a single /Pg entry and plain MCID kids.
                $groupKey = $pageIndex . ':' . ($annotation->taggedGroupKey ?? $annotationKey);

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
        if (!$document->profile->requiresTaggedPageAnnotations()) {
            return [
                'entries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
                'nextStructParentId' => $nextStructParentId,
            ];
        }

        $entries = [];
        $parentTreeEntries = [];
        $structParentIds = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                if ($annotation instanceof LinkAnnotation || !$this->supportsTaggedPageAnnotation($document, $annotation)) {
                    continue;
                }

                $altText = $this->taggedPageAnnotationAltText($document, $annotation);

                if ($altText === null || $altText === '') {
                    continue;
                }

                $entryKey = 'annotation:' . $pageIndex . ':' . $annotationIndex;
                $entries[] = [
                    'key' => $entryKey,
                    'pageIndex' => $pageIndex,
                    'annotationIndex' => $annotationIndex,
                    'altText' => $altText,
                    'tag' => $this->taggedPageAnnotationStructureTag($document, $annotation) ?? 'Annot',
                ];
                $structParentIds[$pageIndex . ':' . $annotationIndex] = $nextStructParentId;
                $parentTreeEntries[$nextStructParentId] = [$entryKey];
                $nextStructParentId++;
            }
        }

        return [
            'entries' => $entries,
            'parentTreeEntries' => $parentTreeEntries,
            'structParentIds' => $structParentIds,
            'nextStructParentId' => $nextStructParentId,
        ];
    }

    private function supportsTaggedPageAnnotation(Document $document, object $annotation): bool
    {
        if (!$document->profile->requiresTaggedPageAnnotations() || $annotation instanceof LinkAnnotation) {
            return false;
        }

        if ($document->profile->isPdfA1() && $document->profile->pdfaConformance() === 'A') {
            return $annotation instanceof PageAnnotation
                && $this->pdfA1aPageAnnotationPolicy->supports($annotation);
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation;
    }

    private function taggedPageAnnotationAltText(Document $document, object $annotation): ?string
    {
        if ($document->profile->isPdfA1() && $document->profile->pdfaConformance() === 'A' && $annotation instanceof PageAnnotation) {
            return $this->pdfA1aPageAnnotationPolicy->altText($annotation);
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation
            ? $annotation->taggedAnnotationAltText()
            : null;
    }

    private function taggedPageAnnotationStructureTag(Document $document, object $annotation): ?string
    {
        if ($document->profile->isPdfA1() && $document->profile->pdfaConformance() === 'A' && $annotation instanceof PageAnnotation) {
            return $this->pdfA1aPageAnnotationPolicy->structureTag($annotation);
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation
            ? $annotation->taggedAnnotationStructureTag()
            : null;
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
            if ($field instanceof RadioButtonGroup) {
                foreach ($field->choices as $choiceIndex => $choice) {
                    $annotationObjectId = $acroFormFieldRelatedObjectIds[$fieldIndex][$choiceIndex * 3] ?? null;

                    if ($annotationObjectId === null) {
                        throw new DocumentValidationException(DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID, sprintf(
                            'Tagged form structure requires a widget annotation object for radio button group "%s" choice %d.',
                            $field->name,
                            $choiceIndex + 1,
                        ));
                    }

                    $entryKey = 'form:' . $field->name . ':choice:' . $choiceIndex;
                    $entries[] = [
                        'key' => $entryKey,
                        'pageIndex' => $choice->pageNumber - 1,
                        'annotationObjectId' => $annotationObjectId,
                        'altText' => $choice->alternativeName ?? $field->alternativeName ?? $field->name,
                    ];
                    $structParentIds[$annotationObjectId] = $nextStructParentId;
                    $parentTreeEntries[$nextStructParentId] = [$entryKey];
                    $nextStructParentId++;
                }

                continue;
            }

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
                throw new DocumentValidationException(DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID, sprintf(
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
            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->textStructElemObjectIds[$textEntry['key']],
                new StructElem(
                    $textEntry['tag'],
                    $this->resolveParentObjectId($textEntry['key'], $state),
                    pageObjectId: $state->pageObjectIds[$textEntry['pageIndex']],
                    markedContentId: $textEntry['markedContentId'],
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
                    $state->documentStructElemObjectId,
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

    /**
     * @return list<array{key: string, pageIndex: int, orderIndex: int, sequence: int}>
     */
    private function documentChildEntriesInReadingOrder(DocumentSerializationPlanBuildState $state): array
    {
        $entries = [];
        $sequence = 0;
        $documentChildPositions = $this->documentChildPositions($state);

        if ($state->taggedStructure->documentChildKeysInOrder !== []) {
            foreach ($state->taggedStructure->documentChildKeysInOrder as $key) {
                $position = $documentChildPositions[$key] ?? [
                    'pageIndex' => 0,
                    'orderIndex' => 1000000 + $sequence,
                ];

                $entries[] = [
                    'key' => $key,
                    'pageIndex' => $position['pageIndex'],
                    'orderIndex' => $position['orderIndex'],
                    'sequence' => $sequence++,
                ];
            }
        } else {
            foreach ($state->taggedStructure->pageMarkedContentKeys as $pageIndex => $pageKeys) {
                ksort($pageKeys);

                foreach ($pageKeys as $markedContentId => $key) {
                    $entries[] = [
                        'key' => $this->documentChildKey($key),
                        'pageIndex' => $pageIndex,
                        'orderIndex' => $markedContentId,
                        'sequence' => $sequence++,
                    ];
                }
            }
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

        foreach ($state->taggedPageAnnotationStructure['entries'] as $annotationEntry) {
            $entries[] = [
                'key' => $annotationEntry['key'],
                'pageIndex' => $annotationEntry['pageIndex'],
                'orderIndex' => 1500000 + $annotationEntry['annotationIndex'],
                'sequence' => $sequence++,
            ];
        }

        foreach ($state->taggedFormStructure['entries'] as $formEntry) {
            $entries[] = [
                'key' => $formEntry['key'],
                'pageIndex' => $formEntry['pageIndex'],
                // Widget annotations do not currently expose MCID-based content
                // positions, so keep them after marked page content on the same page.
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

    /**
     * @return array<string, array{pageIndex: int, orderIndex: int}>
     */
    private function documentChildPositions(DocumentSerializationPlanBuildState $state): array
    {
        $positions = [];

        foreach ($state->taggedStructure->pageMarkedContentKeys as $pageIndex => $pageKeys) {
            ksort($pageKeys);

            foreach ($pageKeys as $markedContentId => $key) {
                $documentChildKey = $this->documentChildKey($key);

                if (isset($positions[$documentChildKey])) {
                    continue;
                }

                $positions[$documentChildKey] = [
                    'pageIndex' => $pageIndex,
                    'orderIndex' => $markedContentId,
                ];
            }
        }

        return $positions;
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

        foreach ($this->documentChildEntriesInReadingOrder($state) as $entry) {
            $objectIds[] = $this->resolveDocumentKidObjectId($entry['key'], $state);
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
        $parentKey = $state->taggedStructure->explicitParentKeys[$key] ?? null;

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

    private function documentChildKey(string $key): string
    {
        if (str_starts_with($key, 'list:') && str_contains($key, ':item:')) {
            [$prefix, $listId] = explode(':', $key, 3);

            return $prefix . ':' . $listId;
        }

        if (str_starts_with($key, 'table:')) {
            [$prefix, $tableId] = explode(':', $key, 3);

            return $prefix . ':' . $tableId;
        }

        return $key;
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
