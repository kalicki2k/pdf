<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function ksort;
use function min;
use function str_contains;
use function str_starts_with;
use function usort;

final readonly class TaggedStructureLayoutPolicy
{
    /**
     * @return list<string>
     */
    public function orderedDocumentChildKeys(DocumentSerializationPlanBuildState $state): array
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
            if (isset($state->taggedStructure->explicitParentKeys[$linkEntry['key']])) {
                continue;
            }

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

        return array_map(
            static fn (array $entry): string => $entry['key'],
            $entries,
        );
    }

    /**
     * @return array<string, array{pageIndex: int, orderIndex: int}>
     */
    public function documentChildPositions(DocumentSerializationPlanBuildState $state): array
    {
        $positions = [];

        foreach ($state->taggedStructure->pageMarkedContentKeys as $pageIndex => $pageKeys) {
            ksort($pageKeys);

            foreach ($pageKeys as $markedContentId => $key) {
                if (!isset($positions[$key])) {
                    $positions[$key] = [
                        'pageIndex' => $pageIndex,
                        'orderIndex' => $markedContentId,
                    ];
                }

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

        foreach ($state->taggedLinkStructure['linkEntries'] as $linkEntry) {
            if (isset($positions[$linkEntry['key']])) {
                continue;
            }

            $positions[$linkEntry['key']] = [
                'pageIndex' => $linkEntry['pageIndex'],
                'orderIndex' => $linkEntry['markedContentIds'] !== []
                    ? min($linkEntry['markedContentIds'])
                    : 1000000 + ($linkEntry['annotationIndices'][0] ?? 0),
            ];
        }

        $pendingContainers = $state->taggedStructure->containerEntries;

        while ($pendingContainers !== []) {
            $remainingContainers = [];
            $resolvedContainer = false;

            foreach ($pendingContainers as $containerEntry) {
                $childPositions = [];

                foreach ($containerEntry['childKeys'] as $childKey) {
                    if (isset($positions[$childKey])) {
                        $childPositions[] = $positions[$childKey];
                    }
                }

                if ($childPositions === []) {
                    $remainingContainers[] = $containerEntry;

                    continue;
                }

                usort(
                    $childPositions,
                    static fn (array $left, array $right): int => [$left['pageIndex'], $left['orderIndex']]
                        <=> [$right['pageIndex'], $right['orderIndex']],
                );
                $positions[$containerEntry['key']] = $childPositions[0];
                $resolvedContainer = true;
            }

            if (!$resolvedContainer) {
                break;
            }

            $pendingContainers = $remainingContainers;
        }

        return $positions;
    }

    public function explicitParentKey(string $key, DocumentSerializationPlanBuildState $state): ?string
    {
        return $state->taggedStructure->explicitParentKeys[$key] ?? null;
    }

    public function documentChildKey(string $key): string
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
}
