<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;

use function sprintf;

final class TaggedStructureCollector
{
    public function collect(Document $document): CollectedTaggedStructure
    {
        $figureEntries = [];
        $textEntries = [];
        $listEntries = [];
        $pageMarkedContentKeys = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->images as $imageIndex => $pageImage) {
                if ($pageImage->markedContentId === null) {
                    continue;
                }

                $key = 'figure:' . $pageIndex . ':' . $imageIndex;
                $this->addPageMarkedContentKey($pageMarkedContentKeys, $pageIndex, $pageImage->markedContentId, $key);
                $figureEntries[] = [
                    'key' => $key,
                    'pageIndex' => $pageIndex,
                    'markedContentId' => $pageImage->markedContentId,
                    'altText' => $pageImage->accessibility?->altText,
                ];
            }
        }

        foreach ($document->taggedTextBlocks as $index => $textBlock) {
            $key = 'text:' . $index . ':' . $textBlock->tag . ':' . $textBlock->pageIndex . ':' . $textBlock->markedContentId;
            $this->addPageMarkedContentKey($pageMarkedContentKeys, $textBlock->pageIndex, $textBlock->markedContentId, $key);
            $textEntries[] = [
                'key' => $key,
                'tag' => $textBlock->tag,
                'pageIndex' => $textBlock->pageIndex,
                'markedContentId' => $textBlock->markedContentId,
            ];
        }

        foreach ($document->taggedLists as $taggedList) {
            $listEntry = [
                'key' => $this->listKey($taggedList->listId),
                'listId' => $taggedList->listId,
                'itemEntries' => [],
            ];

            foreach ($taggedList->items as $itemIndex => $item) {
                $labelKey = $this->listLabelKey($taggedList->listId, $itemIndex);
                $bodyKey = $this->listBodyKey($taggedList->listId, $itemIndex);
                $this->addPageMarkedContentKey(
                    $pageMarkedContentKeys,
                    $item->labelReference->pageIndex,
                    $item->labelReference->markedContentId,
                    $labelKey,
                );
                $this->addPageMarkedContentKey(
                    $pageMarkedContentKeys,
                    $item->bodyReference->pageIndex,
                    $item->bodyReference->markedContentId,
                    $bodyKey,
                );

                $listEntry['itemEntries'][] = [
                    'key' => $this->listItemKey($taggedList->listId, $itemIndex),
                    'itemIndex' => $itemIndex,
                    'labelKey' => $labelKey,
                    'bodyKey' => $bodyKey,
                    'labelReference' => $item->labelReference,
                    'bodyReference' => $item->bodyReference,
                ];
            }

            $listEntries[] = $listEntry;
        }

        foreach ($document->taggedTables as $taggedTable) {
            foreach ($taggedTable->captionReferences as $reference) {
                $this->addPageMarkedContentKey(
                    $pageMarkedContentKeys,
                    $reference->pageIndex,
                    $reference->markedContentId,
                    $this->tableCaptionKey($taggedTable->tableId),
                );
            }

            foreach ($this->tableSections($taggedTable) as $section => $rows) {
                foreach ($rows as $row) {
                    foreach ($row->cells as $cell) {
                        $cellKey = $this->tableCellKey($taggedTable->tableId, $section, $row->rowIndex, $cell->columnIndex);

                        foreach ($cell->contentReferences as $reference) {
                            $this->addPageMarkedContentKey(
                                $pageMarkedContentKeys,
                                $reference->pageIndex,
                                $reference->markedContentId,
                                $cellKey,
                            );
                        }
                    }
                }
            }
        }

        return new CollectedTaggedStructure(
            $figureEntries,
            $textEntries,
            $listEntries,
            $pageMarkedContentKeys,
        );
    }

    /**
     * @param array<int, array<int, string>> $pageMarkedContentKeys
     */
    private function addPageMarkedContentKey(array &$pageMarkedContentKeys, int $pageIndex, int $markedContentId, string $key): void
    {
        if (isset($pageMarkedContentKeys[$pageIndex][$markedContentId])) {
            throw new InvalidArgumentException(sprintf(
                'Duplicate marked-content id %d on page %d.',
                $markedContentId,
                $pageIndex + 1,
            ));
        }

        $pageMarkedContentKeys[$pageIndex][$markedContentId] = $key;
    }

    private function listKey(int $listId): string
    {
        return 'list:' . $listId;
    }

    private function listItemKey(int $listId, int $itemIndex): string
    {
        return 'list:' . $listId . ':item:' . $itemIndex;
    }

    private function listLabelKey(int $listId, int $itemIndex): string
    {
        return 'list:' . $listId . ':item:' . $itemIndex . ':label';
    }

    private function listBodyKey(int $listId, int $itemIndex): string
    {
        return 'list:' . $listId . ':item:' . $itemIndex . ':body';
    }

    private function tableCaptionKey(int $tableId): string
    {
        return 'table:' . $tableId . ':caption';
    }

    private function tableCellKey(int $tableId, string $section, int $rowIndex, int $columnIndex): string
    {
        return 'table:' . $tableId . ':' . $section . ':cell:' . $rowIndex . ':' . $columnIndex;
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
}
