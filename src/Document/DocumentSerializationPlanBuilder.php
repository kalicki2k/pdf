<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function implode;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileStructure;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Trailer;

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
        /** @var array<string, int> $fontDescriptorObjectIds */
        $fontDescriptorObjectIds = [];
        /** @var array<string, int> $fontFileObjectIds */
        $fontFileObjectIds = [];
        /** @var array<string, int> $cidFontObjectIds */
        $cidFontObjectIds = [];
        /** @var array<string, int> $toUnicodeObjectIds */
        $toUnicodeObjectIds = [];
        /** @var array<string, int> $cidToGidMapObjectIds */
        $cidToGidMapObjectIds = [];

        foreach ($document->pages as $page) {
            $pageObjectIds[] = $nextObjectId;
            $nextObjectId++;
            $contentObjectIds[] = $nextObjectId;
            $nextObjectId++;
        }

        foreach ($document->pages as $page) {
            foreach ($page->fontResources as $pageFont) {
                $fontKey = $this->fontObjectKey($pageFont);

                if (!isset($fontObjectIds[$fontKey])) {
                    $fontObjectIds[$fontKey] = $nextObjectId;
                    $nextObjectId++;

                    if ($pageFont->isEmbedded()) {
                        if ($pageFont->usesUnicodeCids()) {
                            $cidFontObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontDescriptorObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontFileObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $toUnicodeObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $cidToGidMapObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                        } else {
                            $fontDescriptorObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontFileObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                        }
                    }
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

        foreach ($this->collectFonts($document->pages) as $fontKey => $pageFont) {
            $fontObjectId = $fontObjectIds[$fontKey];

            if ($pageFont->isEmbedded()) {
                $embeddedFont = $pageFont->embeddedDefinition();
                $fontDescriptorObjectId = $fontDescriptorObjectIds[$fontKey];
                $fontFileObjectId = $fontFileObjectIds[$fontKey];

                if ($pageFont->usesUnicodeCids()) {
                    /** @var list<int> $unicodeCodePoints */
                    $unicodeCodePoints = $pageFont->unicodeCodePoints;
                    $cidFontObjectId = $cidFontObjectIds[$fontKey];
                    $toUnicodeObjectId = $toUnicodeObjectIds[$fontKey];
                    $cidToGidMapObjectId = $cidToGidMapObjectIds[$fontKey];
                    $subsetFontName = $embeddedFont->subsetPostScriptName($unicodeCodePoints);

                    $objects[] = new IndirectObject(
                        $fontObjectId,
                        $embeddedFont->unicodeType0FontObjectContents($cidFontObjectId, $toUnicodeObjectId, $unicodeCodePoints),
                    );
                    $objects[] = new IndirectObject(
                        $cidFontObjectId,
                        $embeddedFont->unicodeCidFontObjectContents(
                            $fontDescriptorObjectId,
                            $cidToGidMapObjectId,
                            $unicodeCodePoints,
                        ),
                    );
                    $objects[] = new IndirectObject(
                        $fontDescriptorObjectId,
                        $embeddedFont->fontDescriptorContents($fontFileObjectId, $subsetFontName),
                    );
                    $objects[] = new IndirectObject(
                        $fontFileObjectId,
                        $embeddedFont->unicodeSubsetFontFileStreamContents($unicodeCodePoints),
                    );
                    $objects[] = new IndirectObject(
                        $toUnicodeObjectId,
                        $embeddedFont->unicodeToUnicodeStreamContents($unicodeCodePoints),
                    );
                    $objects[] = new IndirectObject(
                        $cidToGidMapObjectId,
                        $embeddedFont->unicodeCidToGidMapStreamContents($unicodeCodePoints),
                    );

                    continue;
                }

                $objects[] = new IndirectObject($fontObjectId, $embeddedFont->fontObjectContents($fontDescriptorObjectId));
                $objects[] = new IndirectObject($fontDescriptorObjectId, $embeddedFont->fontDescriptorContents($fontFileObjectId));
                $objects[] = new IndirectObject($fontFileObjectId, $embeddedFont->fontFileStreamContents());

                continue;
            }

            $objects[] = new IndirectObject($fontObjectId, $pageFont->pdfObjectContents());
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
            . 'endstream';
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

        if ($color === null) {
            return '';
        }

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
     * @param array<string, PageFont> $fontResources
     * @param array<string, int> $fontObjectIds
     */
    private function buildPageResources(array $fontResources, array $fontObjectIds): string
    {
        if ($fontResources === []) {
            return '<< >>';
        }

        $entries = [];

        foreach ($fontResources as $fontAlias => $pageFont) {
            $entries[] = '/' . $fontAlias . ' ' . $fontObjectIds[$this->fontObjectKey($pageFont)] . ' 0 R';
        }

        return '<< /Font << ' . implode(' ', $entries) . ' >> >>';
    }

    private function fontObjectKey(PageFont $pageFont): string
    {
        return $pageFont->key();
    }

    /**
     * @param list<Page> $pages
     * @return array<string, PageFont>
     */
    private function collectFonts(array $pages): array
    {
        $fonts = [];

        foreach ($pages as $page) {
            foreach ($page->fontResources as $pageFont) {
                $fontKey = $this->fontObjectKey($pageFont);

                if (!isset($fonts[$fontKey])) {
                    $fonts[$fontKey] = $pageFont;

                    continue;
                }

                if ($pageFont->isEmbedded() && $pageFont->usesUnicodeCids()) {
                    /** @var list<int> $unicodeCodePoints */
                    $unicodeCodePoints = $pageFont->unicodeCodePoints;
                    $fonts[$fontKey] = $fonts[$fontKey]->withAdditionalUnicodeCodePoints($unicodeCodePoints);
                }
            }
        }

        return $fonts;
    }
}
