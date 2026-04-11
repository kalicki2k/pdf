<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Color;
use Kalle\Pdf\ColorSpace;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\StandardFontEncoding;
use function count;
use function implode;

use Kalle\Pdf\Render\DocumentSerializationPlan;

use Kalle\Pdf\Render\FileStructure;
use Kalle\Pdf\Render\IndirectObject;
use Kalle\Pdf\Render\Trailer;

use function str_replace;

/**
 * Builds a minimal serialization plan from a prepared document.
 */
final class DocumentSerializationPlanBuilder
{
    public function build(Document $document): DocumentSerializationPlan
    {
        $pageObjectIds = [];
        $contentObjectIds = [];
        $nextObjectId = 3;
        /** @var array<string, int> $fontObjectIds */
        $fontObjectIds = [];

        foreach ($document->pages as $page) {
            $pageObjectIds[] = $nextObjectId;
            $nextObjectId++;
            $contentObjectIds[] = $nextObjectId;
            $nextObjectId++;
        }

        foreach ($document->pages as $page) {
            foreach ($page->fontResources as $fontName) {
                if (!isset($fontObjectIds[$fontName])) {
                    $fontObjectIds[$fontName] = $nextObjectId;
                    $nextObjectId++;
                }
            }
        }

        $objects = [
            new IndirectObject(1, '<< /Type /Catalog /Pages 2 0 R >>'),
            new IndirectObject(
                2,
                '<< /Type /Pages /Count ' . count($pageObjectIds) . ' /Kids [' . $this->buildKidsReferences($pageObjectIds) . '] >>',
            ),
        ];

        foreach ($document->pages as $index => $page) {
            $pageObjectId = $pageObjectIds[$index];
            $contentObjectId = $contentObjectIds[$index];

            $objects[] = new IndirectObject(
                $pageObjectId,
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $this->formatNumber($page->size->width()) . ' '
                . $this->formatNumber($page->size->height()) . '] /Resources '
                . $this->buildPageResources($page->fontResources, $fontObjectIds) . ' /Contents '
                . $contentObjectId . ' 0 R >>',
            );
            $objects[] = new IndirectObject(
                $contentObjectId,
                $this->buildContentStream($this->buildPageContents($page)),
            );
        }

        foreach ($fontObjectIds as $fontName => $fontObjectId) {
            $encoding = StandardFontEncoding::forFont($fontName, $document->version());
            $objects[] = new IndirectObject(
                $fontObjectId,
                '<< /Type /Font /Subtype /Type1 /BaseFont /' . $fontName . ' /Encoding /' . $encoding->value . ' >>',
            );
        }

        $infoObjectId = null;

        if ($this->hasInfoMetadata($document)) {
            $infoObjectId = $nextObjectId;
            $objects[] = new IndirectObject($infoObjectId, $this->buildInfoDictionary($document));
        }

        return new DocumentSerializationPlan(
            objects: $objects,
            fileStructure: new FileStructure(
                version: $document->version(),
                trailer: new Trailer(
                    size: count($objects) + 1,
                    rootObjectId: 1,
                    infoObjectId: $infoObjectId,
                ),
            ),
        );
    }

    private function hasInfoMetadata(Document $document): bool
    {
        return $document->title !== null
            || $document->author !== null
            || $document->subject !== null
            || $document->creator !== null
            || $document->creatorTool !== null;
    }

    private function buildInfoDictionary(Document $document): string
    {
        $entries = [];

        if ($document->title !== null) {
            $entries[] = '/Title ' . $this->pdfString($document->title);
        }

        if ($document->author !== null) {
            $entries[] = '/Author ' . $this->pdfString($document->author);
        }

        if ($document->subject !== null) {
            $entries[] = '/Subject ' . $this->pdfString($document->subject);
        }

        if ($document->creator !== null) {
            $entries[] = '/Creator ' . $this->pdfString($document->creator);
        }

        if ($document->creatorTool !== null) {
            $entries[] = '/Producer ' . $this->pdfString($document->creatorTool);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @param list<int> $pageObjectIds
     */
    private function buildKidsReferences(array $pageObjectIds): string
    {
        if ($pageObjectIds === []) {
            return '';
        }

        return implode(' ', array_map(
            static fn (int $objectId): string => $objectId . ' 0 R',
            $pageObjectIds,
        ));
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

    private function buildContentStream(string $contents): string
    {
        $normalizedContents = $contents;

        if ($normalizedContents !== '' && !str_ends_with($normalizedContents, "\n")) {
            $normalizedContents .= "\n";
        }

        return '<< /Length ' . strlen($normalizedContents) . " >>\nstream\n"
            . $normalizedContents
            . "endstream";
    }

    private function buildPageContents(Page $page): string
    {
        $contents = $page->contents;

        if ($page->backgroundColor === null) {
            return $contents;
        }

        $backgroundContents = $this->buildBackgroundContents($page);

        if ($contents === '') {
            return $backgroundContents;
        }

        return $backgroundContents . "\n" . $contents;
    }

    private function buildBackgroundContents(Page $page): string
    {
        $color = $page->backgroundColor;

        return implode("\n", [
            'q',
            $this->buildFillColorOperator($color),
            '0 0 ' . $this->formatNumber($page->size->width()) . ' ' . $this->formatNumber($page->size->height()) . ' re',
            'f',
            'Q',
        ]);
    }

    private function buildFillColorOperator(Color $color): string
    {
        $components = array_map(
            fn (float $value): string => $this->formatNumber($value),
            $color->components(),
        );

        return match ($color->space) {
            ColorSpace::GRAY => implode(' ', $components) . ' g',
            ColorSpace::RGB => implode(' ', $components) . ' rg',
            ColorSpace::CMYK => implode(' ', $components) . ' k',
        };
    }

    /**
     * @param array<string, string> $fontResources
     * @param array<string, int> $fontObjectIds
     */
    private function buildPageResources(array $fontResources, array $fontObjectIds): string
    {
        if ($fontResources === []) {
            return '<< >>';
        }

        $entries = [];

        foreach ($fontResources as $fontAlias => $fontName) {
            $entries[] = '/' . $fontAlias . ' ' . $fontObjectIds[$fontName] . ' 0 R';
        }

        return '<< /Font << ' . implode(' ', $entries) . ' >> >>';
    }
}
