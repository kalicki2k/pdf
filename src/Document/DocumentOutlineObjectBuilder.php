<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_key_exists;

use function count;
use function implode;

use Kalle\Pdf\Writer\IndirectObject;

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
     *   visibleDescendantCounts: array<int, int>,
     *   rootVisibleCount: int
     * } $tree
     */
    private function buildRootDictionary(
        DocumentSerializationPlanBuildState $state,
        array $tree,
    ): string {
        $rootChildren = $tree['rootChildren'];

        return '<< /Type /Outlines /First '
            . $state->outlineItemObjectIds[$rootChildren[0]]
            . ' 0 R /Last '
            . $state->outlineItemObjectIds[$rootChildren[count($rootChildren) - 1]]
            . ' 0 R /Count '
            . $tree['rootVisibleCount']
            . ' >>';
    }

    /**
     * @param array{
     *   parentIndices: array<int, ?int>,
     *   children: array<int, list<int>>,
     *   rootChildren: list<int>,
     *   visibleDescendantCounts: array<int, int>,
     *   rootVisibleCount: int
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
        ];

        $destination = $this->buildDestination($document, $state, $outline);
        $entries[] = $this->buildDestinationEntry($outline, $destination);

        if ($previousIndex !== null) {
            $entries[] = '/Prev ' . $state->outlineItemObjectIds[$previousIndex] . ' 0 R';
        }

        if ($nextIndex !== null) {
            $entries[] = '/Next ' . $state->outlineItemObjectIds[$nextIndex] . ' 0 R';
        }

        if ($children !== []) {
            $entries[] = '/First ' . $state->outlineItemObjectIds[$children[0]] . ' 0 R';
            $entries[] = '/Last ' . $state->outlineItemObjectIds[$children[count($children) - 1]] . ' 0 R';
            $entries[] = '/Count ' . ($outline->open
                ? $tree['visibleDescendantCounts'][$outlineIndex]
                : -$tree['visibleDescendantCounts'][$outlineIndex]);
        }

        $styleEntries = $this->buildStyleEntries($outline);
        $entries = [...$entries, ...$styleEntries];

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @return array{
     *   parentIndices: array<int, ?int>,
     *   children: array<int, list<int>>,
     *   rootChildren: list<int>,
     *   visibleDescendantCounts: array<int, int>,
     *   rootVisibleCount: int
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

        $visibleDescendantCounts = [];

        foreach (array_keys($document->outlines) as $index) {
            $visibleDescendantCounts[$index] = $this->countVisibleDescendants($document, $index, $children);
        }

        return [
            'parentIndices' => $parentIndices,
            'children' => $children,
            'rootChildren' => $rootChildren,
            'visibleDescendantCounts' => $visibleDescendantCounts,
            'rootVisibleCount' => $this->countVisibleChildren($document, $rootChildren, $children),
        ];
    }

    /**
     * @param array<int, list<int>> $children
     */
    private function countVisibleDescendants(Document $document, int $index, array $children): int
    {
        if (!array_key_exists($index, $children)) {
            return 0;
        }

        return $this->countVisibleChildren($document, $children[$index], $children);
    }

    /**
     * @param list<int> $childIndices
     * @param array<int, list<int>> $children
     */
    private function countVisibleChildren(Document $document, array $childIndices, array $children): int
    {
        $count = count($childIndices);

        foreach ($childIndices as $childIndex) {
            if (!$document->outlines[$childIndex]->open) {
                continue;
            }

            $count += $this->countVisibleDescendants($document, $childIndex, $children);
        }

        return $count;
    }

    private function buildDestination(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        Outline $outline,
    ): string {
        if ($outline->destination->isNamed()) {
            return '/' . $this->pdfName($outline->destination->namedDestination ?? '');
        }

        $pageIndex = $outline->destination->pageNumber - 1;
        $pageReference = $outline->destination->isRemote()
            ? (string) ($outline->destination->pageNumber - 1)
            : $state->pageObjectIds[$pageIndex] . ' 0 R';

        if ($outline->destination->isFit()) {
            return '[' . $pageReference . ' /Fit]';
        }

        if ($outline->destination->isFitHorizontal()) {
            return '['
                . $pageReference
                . ' /FitH '
                . $this->formatNumber($outline->destination->top ?? 0.0)
                . ']';
        }

        if ($outline->destination->isFitRectangle()) {
            return '['
                . $pageReference
                . ' /FitR '
                . $this->formatNumber($outline->destination->left ?? 0.0)
                . ' '
                . $this->formatNumber($outline->destination->bottom ?? 0.0)
                . ' '
                . $this->formatNumber($outline->destination->right ?? 0.0)
                . ' '
                . $this->formatNumber($outline->destination->top ?? 0.0)
                . ']';
        }

        return '['
            . $pageReference
            . ' /XYZ '
            . $this->formatNumber($outline->destination->x ?? 0.0)
            . ' '
            . $this->formatNumber($outline->destination->y ?? $document->pages[$pageIndex]->size->height())
            . ' null]';
    }

    private function buildDestinationEntry(Outline $outline, string $destination): string
    {
        if (!$outline->destination->useGoToAction) {
            return '/Dest ' . $destination;
        }

        if ($outline->destination->isRemote()) {
            $entries = [
                '/S /GoToR',
                '/F ' . $this->pdfString($outline->destination->remoteFile ?? ''),
                '/D ' . $destination,
            ];

            if ($outline->destination->newWindow) {
                $entries[] = '/NewWindow true';
            }

            return '/A << ' . implode(' ', $entries) . ' >>';
        }

        return '/A << /S /GoTo /D ' . $destination . ' >>';
    }

    /**
     * @return list<string>
     */
    private function buildStyleEntries(Outline $outline): array
    {
        if ($outline->style === null) {
            return [];
        }

        $entries = [];
        $rgbComponents = $outline->style->pdfRgbComponents();

        if ($rgbComponents !== null) {
            $entries[] = '/C [' . implode(' ', array_map($this->formatNumber(...), $rgbComponents)) . ']';
        }

        $flags = $outline->style->pdfFlags();

        if ($flags !== 0) {
            $entries[] = '/F ' . $flags;
        }

        return $entries;
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

    private function pdfName(string $value): string
    {
        $encoded = '';

        foreach (str_split($value) as $character) {
            $ord = ord($character);

            if (
                ($ord >= 48 && $ord <= 57)
                || ($ord >= 65 && $ord <= 90)
                || ($ord >= 97 && $ord <= 122)
                || $character === '-'
                || $character === '_'
                || $character === '.'
            ) {
                $encoded .= $character;

                continue;
            }

            $encoded .= '#' . strtoupper(str_pad(dechex($ord), 2, '0', STR_PAD_LEFT));
        }

        return $encoded;
    }
}
