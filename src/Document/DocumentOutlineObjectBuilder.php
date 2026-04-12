<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Writer\IndirectObject;

use function array_key_exists;
use function count;
use function implode;
use function number_format;
use function rtrim;
use function str_replace;

final class DocumentOutlineObjectBuilder
{
    /**
     * @return list<string>
     */
    public function buildCatalogEntries(?int $outlineRootObjectId): array
    {
        if ($outlineRootObjectId === null) {
            return [];
        }

        return ['/Outlines ' . $outlineRootObjectId . ' 0 R'];
    }

    /**
     * @return list<IndirectObject>
     */
    public function buildObjects(Document $document, DocumentSerializationPlanBuildState $state): array
    {
        if ($state->outlineRootObjectId === null || $state->outlineItemObjectIds === []) {
            return [];
        }

        $tree = $this->buildTree($document);
        $objects = [
            IndirectObject::plain(
                $state->outlineRootObjectId,
                $this->buildRootDictionary($state, $tree),
            ),
        ];

        foreach ($document->outlines as $index => $outline) {
            $objects[] = IndirectObject::plain(
                $state->outlineItemObjectIds[$index],
                $this->buildItemDictionary(
                    $document,
                    $state,
                    $outline,
                    $index,
                    $tree,
                ),
            );
        }

        return $objects;
    }

    /**
     * @param array{
     *   parentIndices: array<int, ?int>,
     *   children: array<int, list<int>>,
     *   rootChildren: list<int>,
     *   descendantCounts: array<int, int>
     * } $tree
     */
    private function buildRootDictionary(
        DocumentSerializationPlanBuildState $state,
        array $tree,
    ): string
    {
        $rootChildren = $tree['rootChildren'];

        return '<< /Type /Outlines /First '
            . $state->outlineItemObjectIds[$rootChildren[0]]
            . ' 0 R /Last '
            . $state->outlineItemObjectIds[$rootChildren[count($rootChildren) - 1]]
            . ' 0 R /Count '
            . count($state->outlineItemObjectIds)
            . ' >>';
    }

    /**
     * @param array{
     *   parentIndices: array<int, ?int>,
     *   children: array<int, list<int>>,
     *   rootChildren: list<int>,
     *   descendantCounts: array<int, int>
     * } $tree
     */
    private function buildItemDictionary(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        Outline $outline,
        int $outlineIndex,
        array $tree,
    ): string {
        $parentIndex = $tree['parentIndices'][$outlineIndex];
        $siblings = $parentIndex === null
            ? $tree['rootChildren']
            : ($tree['children'][$parentIndex] ?? []);
        $siblingOffset = array_search($outlineIndex, $siblings, true);
        $previousIndex = $siblingOffset !== false && $siblingOffset > 0 ? $siblings[$siblingOffset - 1] : null;
        $nextIndex = $siblingOffset !== false && $siblingOffset < count($siblings) - 1 ? $siblings[$siblingOffset + 1] : null;
        $children = $tree['children'][$outlineIndex] ?? [];
        $entries = [
            '/Title ' . $this->pdfString($outline->title),
            '/Parent ' . ($parentIndex === null
                ? $state->outlineRootObjectId . ' 0 R'
                : $state->outlineItemObjectIds[$parentIndex] . ' 0 R'),
            '/Dest ' . $this->buildDestination($document, $state, $outline),
        ];

        if ($previousIndex !== null) {
            $entries[] = '/Prev ' . $state->outlineItemObjectIds[$previousIndex] . ' 0 R';
        }

        if ($nextIndex !== null) {
            $entries[] = '/Next ' . $state->outlineItemObjectIds[$nextIndex] . ' 0 R';
        }

        if ($children !== []) {
            $entries[] = '/First ' . $state->outlineItemObjectIds[$children[0]] . ' 0 R';
            $entries[] = '/Last ' . $state->outlineItemObjectIds[$children[count($children) - 1]] . ' 0 R';
            $entries[] = '/Count ' . $tree['descendantCounts'][$outlineIndex];
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @return array{
     *   parentIndices: array<int, ?int>,
     *   children: array<int, list<int>>,
     *   rootChildren: list<int>,
     *   descendantCounts: array<int, int>
     * }
     */
    private function buildTree(Document $document): array
    {
        $parentIndices = [];
        $children = [];
        $rootChildren = [];
        $lastIndexByLevel = [];

        foreach ($document->outlines as $index => $outline) {
            $parentIndex = $outline->level === 1 ? null : ($lastIndexByLevel[$outline->level - 1] ?? null);
            $parentIndices[$index] = $parentIndex;

            if ($parentIndex === null) {
                $rootChildren[] = $index;
            } else {
                $children[$parentIndex] ??= [];
                $children[$parentIndex][] = $index;
            }

            $lastIndexByLevel[$outline->level] = $index;

            foreach (array_keys($lastIndexByLevel) as $level) {
                if ($level <= $outline->level) {
                    continue;
                }

                unset($lastIndexByLevel[$level]);
            }
        }

        $descendantCounts = [];

        foreach (array_keys($document->outlines) as $index) {
            $descendantCounts[$index] = $this->countDescendants($index, $children);
        }

        return [
            'parentIndices' => $parentIndices,
            'children' => $children,
            'rootChildren' => $rootChildren,
            'descendantCounts' => $descendantCounts,
        ];
    }

    /**
     * @param array<int, list<int>> $children
     */
    private function countDescendants(int $index, array $children): int
    {
        if (!array_key_exists($index, $children)) {
            return 0;
        }

        $count = count($children[$index]);

        foreach ($children[$index] as $childIndex) {
            $count += $this->countDescendants($childIndex, $children);
        }

        return $count;
    }

    private function buildDestination(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        Outline $outline,
    ): string {
        $pageIndex = $outline->pageNumber - 1;
        $pageObjectId = $state->pageObjectIds[$pageIndex];

        if ($outline->hasPosition()) {
            return '['
                . $pageObjectId
                . ' 0 R /XYZ '
                . $this->formatNumber($outline->x ?? 0.0)
                . ' '
                . $this->formatNumber($outline->y ?? 0.0)
                . ' null]';
        }

        return '['
            . $pageObjectId
            . ' 0 R /XYZ 0 '
            . $this->formatNumber($document->pages[$pageIndex]->size->height())
            . ' null]';
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }
}
