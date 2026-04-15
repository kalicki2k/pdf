<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_last;
use function array_map;
use function array_values;
use function preg_match;

use Kalle\Pdf\Page\LinkAnnotation;

final class TaggedLinkStructureCollector
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
    public function collect(Document $document, int $nextStructParentId): array
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
        $structParentRegistry = new TaggedAnnotationStructParentRegistry($nextStructParentId);

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                if (!$annotation instanceof LinkAnnotation) {
                    continue;
                }

                $annotationKey = $pageIndex . ':' . $annotationIndex;
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
                        : array_last($groupedLinkEntries[$groupKey]['altTextParts']);

                    if ($lastAltTextPart !== $accessibleLabel) {
                        $groupedLinkEntries[$groupKey]['altTextParts'][] = $accessibleLabel;
                    }
                }

                if ($annotation->markedContentId() !== null) {
                    $groupedLinkEntries[$groupKey]['markedContentIds'][] = $annotation->markedContentId();
                }

                $structParentRegistry->register($annotationKey, $groupKey);
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
            'parentTreeEntries' => $structParentRegistry->parentTreeEntries(),
            'structParentIds' => $structParentRegistry->stringStructParentIds(),
            'nextStructParentId' => $structParentRegistry->nextStructParentId(),
        ];
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
}
