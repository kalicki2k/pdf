<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_map;
use function count;

use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Writer\IndirectObject;

final class TaggedMarkedContentStructElemObjectBuilder
{
    /**
     * @param array{key: string, pageIndex: int, markedContentId: int, altText: ?string} $figureEntry
     */
    public function buildFigureObject(
        array $figureEntry,
        DocumentSerializationPlanBuildState $state,
        int $parentObjectId,
    ): IndirectObject {
        return new IndirectObject(
            $state->taggedStructureObjectIds->figureStructElemObjectIds[$figureEntry['key']],
            new StructElem(
                'Figure',
                $parentObjectId,
                pageObjectId: $state->pageObjectIds[$figureEntry['pageIndex']],
                altText: $figureEntry['altText'],
                markedContentId: $figureEntry['markedContentId'],
            )->objectContents(),
        );
    }

    /**
     * @param array{
     *   key: string,
     *   tag: string,
     *   references: list<array{pageIndex: int, markedContentId: int}>
     * } $textEntry
     * @param callable(list<object{pageIndex: int, markedContentId: int}>, list<int>): list<string> $taggedMarkedContentKidEntries
     */
    public function buildTextObject(
        array $textEntry,
        DocumentSerializationPlanBuildState $state,
        int $parentObjectId,
        callable $taggedMarkedContentKidEntries,
    ): IndirectObject {
        $references = $textEntry['references'];

        return new IndirectObject(
            $state->taggedStructureObjectIds->textStructElemObjectIds[$textEntry['key']],
            new StructElem(
                $textEntry['tag'],
                $parentObjectId,
                pageObjectId: count($references) === 1 ? $state->pageObjectIds[$references[0]['pageIndex']] : null,
                markedContentId: count($references) === 1 ? $references[0]['markedContentId'] : null,
                kidEntries: count($references) > 1
                    ? $taggedMarkedContentKidEntries(
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
}
