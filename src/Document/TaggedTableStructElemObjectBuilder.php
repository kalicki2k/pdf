<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureObjectIds;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Writer\IndirectObject;

final class TaggedTableStructElemObjectBuilder
{
    /**
     * @return list<IndirectObject>
     */
    public function buildObjects(
        Document $document,
        TaggedTable $taggedTable,
        DocumentSerializationPlanBuildState $state,
        int $parentObjectId,
    ): array {
        $tableStructKey = $taggedTable->key ?? TaggedStructureObjectIds::tableKey($taggedTable->tableId);
        $tableKidObjectIds = [];

        if ($taggedTable->hasCaption()) {
            $tableKidObjectIds[] = $state->taggedStructureObjectIds->captionStructElemObjectIds[
                TaggedStructureObjectIds::tableCaptionKey($taggedTable->tableId)
            ];
        }

        foreach ($this->tableSections($taggedTable) as $section => $rows) {
            if ($rows !== []) {
                $tableKidObjectIds[] = $state->taggedStructureObjectIds->tableSectionStructElemObjectIds[
                    TaggedStructureObjectIds::tableSectionKey($taggedTable->tableId, $section)
                ];
            }
        }

        $objects = [
            new IndirectObject(
                $state->taggedStructureObjectIds->tableStructElemObjectIds[$tableStructKey],
                new StructElem('Table', $parentObjectId, $tableKidObjectIds)->objectContents(),
            ),
        ];

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

        foreach ($this->tableSections($taggedTable) as $section => $rows) {
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
                    $this->sectionTag($document, $section),
                    $state->taggedStructureObjectIds->tableStructElemObjectIds[$tableStructKey],
                    $sectionKidObjectIds,
                )->objectContents(),
            );

            foreach ($rows as $row) {
                $objects = [...$objects, ...$this->buildRowObjects($taggedTable, $sectionKey, $section, $row, $state)];
            }
        }

        return $objects;
    }

    /**
     * @return list<IndirectObject>
     */
    private function buildRowObjects(
        TaggedTable $taggedTable,
        string $sectionKey,
        string $section,
        TaggedTableRow $row,
        DocumentSerializationPlanBuildState $state,
    ): array {
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

        $objects = [
            new IndirectObject(
                $state->taggedStructureObjectIds->rowStructElemObjectIds[$rowKey],
                new StructElem('TR', $state->taggedStructureObjectIds->tableSectionStructElemObjectIds[$sectionKey], $rowKidObjectIds)->objectContents(),
            ),
        ];

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

        return $objects;
    }

    /**
     * @return array<string, list<TaggedTableRow>>
     */
    private function tableSections(TaggedTable $taggedTable): array
    {
        return [
            'header' => $taggedTable->headerRows,
            'body' => $taggedTable->bodyRows,
            'footer' => $taggedTable->footerRows,
        ];
    }

    private function sectionTag(Document $document, string $section): string
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
