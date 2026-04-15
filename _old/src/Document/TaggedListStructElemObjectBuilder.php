<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Writer\IndirectObject;

final class TaggedListStructElemObjectBuilder
{
    /**
     * @param array{
     *   key: string,
     *   itemEntries: list<array{
     *     key: string,
     *     labelKey: string,
     *     bodyKey: string,
     *     labelReference: object{pageIndex: int, markedContentId: int},
     *     bodyReference: object{pageIndex: int, markedContentId: int}
     *   }>
     * } $listEntry
     * @param callable(list<object{pageIndex: int, markedContentId: int}>, list<int>): list<string> $taggedMarkedContentKidEntries
     * @return list<IndirectObject>
     */
    public function buildObjects(
        array $listEntry,
        DocumentSerializationPlanBuildState $state,
        int $parentObjectId,
        callable $taggedMarkedContentKidEntries,
    ): array {
        $listKidObjectIds = [];

        foreach ($listEntry['itemEntries'] as $itemEntry) {
            $listKidObjectIds[] = $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']];
        }

        $objects = [
            new IndirectObject(
                $state->taggedStructureObjectIds->listStructElemObjectIds[$listEntry['key']],
                new StructElem('L', $parentObjectId, $listKidObjectIds)->objectContents(),
            ),
        ];

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
                    kidEntries: $taggedMarkedContentKidEntries([$itemEntry['labelReference']], $state->pageObjectIds),
                )->objectContents(),
            );
            $objects[] = new IndirectObject(
                $state->taggedStructureObjectIds->listBodyStructElemObjectIds[$itemEntry['bodyKey']],
                new StructElem(
                    'LBody',
                    $state->taggedStructureObjectIds->listItemStructElemObjectIds[$itemEntry['key']],
                    kidEntries: $taggedMarkedContentKidEntries([$itemEntry['bodyReference']], $state->pageObjectIds),
                )->objectContents(),
            );
        }

        return $objects;
    }
}
