<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;

final readonly class TaggedStructureObjectIds
{
    /**
     * @param array<string, int> $figureStructElemObjectIds
     * @param array<string, int> $textStructElemObjectIds
     * @param array<string, int> $listStructElemObjectIds
     * @param array<string, int> $listItemStructElemObjectIds
     * @param array<string, int> $listLabelStructElemObjectIds
     * @param array<string, int> $listBodyStructElemObjectIds
     * @param array<string, int> $tableStructElemObjectIds
     * @param array<string, int> $captionStructElemObjectIds
     * @param array<string, int> $tableSectionStructElemObjectIds
     * @param array<string, int> $rowStructElemObjectIds
     * @param array<string, int> $cellStructElemObjectIds
     * @param array<string, int> $linkStructElemObjectIds
     */
    public function __construct(
        public array $figureStructElemObjectIds,
        public array $textStructElemObjectIds,
        public array $listStructElemObjectIds,
        public array $listItemStructElemObjectIds,
        public array $listLabelStructElemObjectIds,
        public array $listBodyStructElemObjectIds,
        public array $tableStructElemObjectIds,
        public array $captionStructElemObjectIds,
        public array $tableSectionStructElemObjectIds,
        public array $rowStructElemObjectIds,
        public array $cellStructElemObjectIds,
        public array $linkStructElemObjectIds,
        public int $nextObjectId,
    ) {
    }

    /**
     * @param list<array{key: string}> $taggedLinkEntries
     */
    public static function allocate(Document $document, CollectedTaggedStructure $structure, array $taggedLinkEntries, int $nextObjectId): self
    {
        $figureStructElemObjectIds = [];
        $textStructElemObjectIds = [];
        $listStructElemObjectIds = [];
        $listItemStructElemObjectIds = [];
        $listLabelStructElemObjectIds = [];
        $listBodyStructElemObjectIds = [];
        $tableStructElemObjectIds = [];
        $captionStructElemObjectIds = [];
        $tableSectionStructElemObjectIds = [];
        $rowStructElemObjectIds = [];
        $cellStructElemObjectIds = [];
        $linkStructElemObjectIds = [];

        foreach ($structure->figureEntries as $figureEntry) {
            $figureStructElemObjectIds[$figureEntry['key']] = $nextObjectId++;
        }

        foreach ($structure->textEntries as $textEntry) {
            $textStructElemObjectIds[$textEntry['key']] = $nextObjectId++;
        }

        foreach ($structure->listEntries as $listEntry) {
            $listStructElemObjectIds[$listEntry['key']] = $nextObjectId++;

            foreach ($listEntry['itemEntries'] as $itemEntry) {
                $listItemStructElemObjectIds[$itemEntry['key']] = $nextObjectId++;
                $listLabelStructElemObjectIds[$itemEntry['labelKey']] = $nextObjectId++;
                $listBodyStructElemObjectIds[$itemEntry['bodyKey']] = $nextObjectId++;
            }
        }

        foreach ($document->taggedTables as $taggedTable) {
            $tableStructElemObjectIds[self::tableKey($taggedTable->tableId)] = $nextObjectId++;

            if ($taggedTable->hasCaption()) {
                $captionStructElemObjectIds[self::tableCaptionKey($taggedTable->tableId)] = $nextObjectId++;
            }

            foreach (self::tableSections($taggedTable) as $section => $rows) {
                if ($rows !== []) {
                    $tableSectionStructElemObjectIds[self::tableSectionKey($taggedTable->tableId, $section)] = $nextObjectId++;
                }

                foreach ($rows as $row) {
                    $rowStructElemObjectIds[self::tableRowKey($taggedTable->tableId, $section, $row->rowIndex)] = $nextObjectId++;

                    foreach ($row->cells as $cell) {
                        $cellStructElemObjectIds[self::tableCellKey(
                            $taggedTable->tableId,
                            $section,
                            $row->rowIndex,
                            $cell->columnIndex,
                        )] = $nextObjectId++;
                    }
                }
            }
        }

        foreach ($taggedLinkEntries as $linkEntry) {
            $linkStructElemObjectIds[$linkEntry['key']] = $nextObjectId++;
        }

        return new self(
            $figureStructElemObjectIds,
            $textStructElemObjectIds,
            $listStructElemObjectIds,
            $listItemStructElemObjectIds,
            $listLabelStructElemObjectIds,
            $listBodyStructElemObjectIds,
            $tableStructElemObjectIds,
            $captionStructElemObjectIds,
            $tableSectionStructElemObjectIds,
            $rowStructElemObjectIds,
            $cellStructElemObjectIds,
            $linkStructElemObjectIds,
            $nextObjectId,
        );
    }

    public function resolvePageContentObjectId(string $key): int
    {
        return $this->figureStructElemObjectIds[$key]
            ?? $this->textStructElemObjectIds[$key]
            ?? $this->listLabelStructElemObjectIds[$key]
            ?? $this->listBodyStructElemObjectIds[$key]
            ?? $this->captionStructElemObjectIds[$key]
            ?? $this->cellStructElemObjectIds[$key]
            ?? throw new InvalidArgumentException("Unknown tagged page content key '$key'.");
    }

    public static function tableKey(int $tableId): string
    {
        return 'table:' . $tableId;
    }

    public static function tableCaptionKey(int $tableId): string
    {
        return 'table:' . $tableId . ':caption';
    }

    public static function tableSectionKey(int $tableId, string $section): string
    {
        return 'table:' . $tableId . ':' . $section . ':section';
    }

    public static function tableRowKey(int $tableId, string $section, int $rowIndex): string
    {
        return 'table:' . $tableId . ':' . $section . ':row:' . $rowIndex;
    }

    public static function tableCellKey(int $tableId, string $section, int $rowIndex, int $columnIndex): string
    {
        return 'table:' . $tableId . ':' . $section . ':cell:' . $rowIndex . ':' . $columnIndex;
    }

    /**
     * @return array<string, list<TaggedTableRow>>
     */
    private static function tableSections(TaggedTable $taggedTable): array
    {
        return [
            'header' => $taggedTable->headerRows,
            'body' => $taggedTable->bodyRows,
            'footer' => $taggedTable->footerRows,
        ];
    }
}
