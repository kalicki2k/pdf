<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Writer\IndirectObject;

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

        $objects = [
            IndirectObject::plain(
                $state->outlineRootObjectId,
                $this->buildRootDictionary($state->outlineItemObjectIds),
            ),
        ];

        foreach ($document->outlines as $index => $outline) {
            $itemObjectId = $state->outlineItemObjectIds[$index];
            $previousObjectId = $index > 0 ? $state->outlineItemObjectIds[$index - 1] : null;
            $nextObjectId = $index < count($state->outlineItemObjectIds) - 1 ? $state->outlineItemObjectIds[$index + 1] : null;

            $objects[] = IndirectObject::plain(
                $itemObjectId,
                $this->buildItemDictionary(
                    $document,
                    $state,
                    $outline,
                    $state->outlineRootObjectId,
                    $previousObjectId,
                    $nextObjectId,
                ),
            );
        }

        return $objects;
    }

    /**
     * @param list<int> $outlineItemObjectIds
     */
    private function buildRootDictionary(array $outlineItemObjectIds): string
    {
        return '<< /Type /Outlines /First '
            . $outlineItemObjectIds[0]
            . ' 0 R /Last '
            . $outlineItemObjectIds[count($outlineItemObjectIds) - 1]
            . ' 0 R /Count '
            . count($outlineItemObjectIds)
            . ' >>';
    }

    private function buildItemDictionary(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        Outline $outline,
        int $outlineRootObjectId,
        ?int $previousObjectId,
        ?int $nextObjectId,
    ): string {
        $entries = [
            '/Title ' . $this->pdfString($outline->title),
            '/Parent ' . $outlineRootObjectId . ' 0 R',
            '/Dest ' . $this->buildDestination($document, $state, $outline),
        ];

        if ($previousObjectId !== null) {
            $entries[] = '/Prev ' . $previousObjectId . ' 0 R';
        }

        if ($nextObjectId !== null) {
            $entries[] = '/Next ' . $nextObjectId . ' 0 R';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
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
