<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use DateTimeImmutable;

use function count;
use function implode;
use function sprintf;

use InvalidArgumentException;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Document\Metadata\IccProfile;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\Metadata\XmpMetadata;
use Kalle\Pdf\Document\TaggedPdf\ParentTree;
use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Document\TaggedPdf\StructTreeRoot;
use Kalle\Pdf\Font\OpenTypeOutlineType;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\EmbeddedGlyph;
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
        $this->assertProfileRequirements($document);
        $this->assertImageAccessibilityRequirements($document);
        $this->assertPdfARequirements($document);
        $serializedAt = new DateTimeImmutable('now');

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
        /** @var array<string, int> $imageObjectIds */
        $imageObjectIds = [];

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
                            $embeddedFont = $pageFont->embeddedDefinition();
                            $cidFontObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontDescriptorObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontFileObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $toUnicodeObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;

                            if ($embeddedFont->metadata->outlineType === OpenTypeOutlineType::TRUE_TYPE) {
                                $cidToGidMapObjectIds[$fontKey] = $nextObjectId;
                                $nextObjectId++;
                            }
                        } else {
                            $fontDescriptorObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontFileObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                        }
                    }
                }
            }

            foreach ($page->imageResources as $imageSource) {
                $nextObjectId = $this->reserveImageObjectIds($imageSource, $imageObjectIds, $nextObjectId);
            }
        }

        $taggedImageStructure = $this->collectTaggedImageStructure($document);
        $structTreeRootObjectId = $document->profile->requiresTaggedPdf() ? $nextObjectId++ : null;
        $documentStructElemObjectId = $document->profile->requiresTaggedPdf() ? $nextObjectId++ : null;
        $parentTreeObjectId = $taggedImageStructure['parentTreeEntries'] !== [] ? $nextObjectId++ : null;
        /** @var array<string, int> $figureStructElemObjectIds */
        $figureStructElemObjectIds = [];

        foreach ($taggedImageStructure['figureEntries'] as $figureEntry) {
            $figureStructElemObjectIds[$figureEntry['key']] = $nextObjectId++;
        }

        $metadataObjectId = $this->usesMetadataStream($document) ? $nextObjectId++ : null;
        $iccProfileObjectId = $document->profile->usesPdfAOutputIntent() ? $nextObjectId++ : null;
        $infoObjectId = null;

        if ($document->profile->writesInfoDictionary() && $this->hasInfoMetadata($document)) {
            $infoObjectId = $nextObjectId++;
        }

        $objects = [
            new IndirectObject(
                1,
                $this->buildCatalogDictionary($document, $metadataObjectId, $iccProfileObjectId, $structTreeRootObjectId),
            ),
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
                . $this->buildPageResources($page->fontResources, $page->imageResources, $fontObjectIds, $imageObjectIds) . ' /Contents '
                . $contentObjectId . ' 0 R'
                . $this->buildStructParentsEntry($taggedImageStructure['pageStructParentIds'][$index] ?? null)
                . ' >>',
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
                    /** @var list<EmbeddedGlyph> $embeddedGlyphs */
                    $embeddedGlyphs = $pageFont->embeddedGlyphs;
                    $cidFontObjectId = $cidFontObjectIds[$fontKey];
                    $toUnicodeObjectId = $toUnicodeObjectIds[$fontKey];
                    $cidToGidMapObjectId = $cidToGidMapObjectIds[$fontKey] ?? null;
                    $subsetFontName = $embeddedFont->unicodeBaseFontNameForGlyphs($embeddedGlyphs);

                    $objects[] = new IndirectObject(
                        $fontObjectId,
                        $embeddedFont->unicodeType0FontObjectContentsForGlyphs($cidFontObjectId, $toUnicodeObjectId, $embeddedGlyphs),
                    );
                    $objects[] = new IndirectObject(
                        $cidFontObjectId,
                        $embeddedFont->unicodeCidFontObjectContentsForGlyphs(
                            $fontDescriptorObjectId,
                            $cidToGidMapObjectId,
                            $embeddedGlyphs,
                        ),
                    );
                    $objects[] = new IndirectObject(
                        $fontDescriptorObjectId,
                        $embeddedFont->fontDescriptorContents($fontFileObjectId, $subsetFontName),
                    );
                    $objects[] = new IndirectObject(
                        $fontFileObjectId,
                        $embeddedFont->unicodeSubsetFontFileStreamContentsForGlyphs($embeddedGlyphs),
                    );
                    $objects[] = new IndirectObject(
                        $toUnicodeObjectId,
                        $embeddedFont->unicodeToUnicodeStreamContentsForGlyphs($embeddedGlyphs),
                    );
                    if ($cidToGidMapObjectId !== null) {
                        $objects[] = new IndirectObject(
                            $cidToGidMapObjectId,
                            $embeddedFont->unicodeCidToGidMapStreamContentsForGlyphs($embeddedGlyphs),
                        );
                    }

                    continue;
                }

                $objects[] = new IndirectObject($fontObjectId, $embeddedFont->fontObjectContents($fontDescriptorObjectId));
                $objects[] = new IndirectObject($fontDescriptorObjectId, $embeddedFont->fontDescriptorContents($fontFileObjectId));
                $objects[] = new IndirectObject($fontFileObjectId, $embeddedFont->fontFileStreamContents());

                continue;
            }

            $objects[] = new IndirectObject($fontObjectId, $pageFont->pdfObjectContents());
        }

        foreach ($this->collectImages($document->pages) as $imageKey => $imageSource) {
            $objects[] = new IndirectObject(
                $imageObjectIds[$imageKey],
                $imageSource->pdfObjectContents(
                    $imageSource->softMask !== null ? $imageObjectIds[$imageSource->softMask->key()] : null,
                ),
            );
        }

        if ($structTreeRootObjectId !== null && $documentStructElemObjectId !== null) {
            $documentKidObjectIds = [];

            foreach ($taggedImageStructure['figureEntries'] as $figureEntry) {
                $documentKidObjectIds[] = $figureStructElemObjectIds[$figureEntry['key']];
            }

            $objects[] = new IndirectObject(
                $structTreeRootObjectId,
                (new StructTreeRoot([$documentStructElemObjectId], $parentTreeObjectId))->objectContents(),
            );
            $objects[] = new IndirectObject(
                $documentStructElemObjectId,
                (new StructElem('Document', $structTreeRootObjectId, $documentKidObjectIds))->objectContents(),
            );

            if ($parentTreeObjectId !== null) {
                $parentTreeEntries = [];

                foreach ($taggedImageStructure['parentTreeEntries'] as $structParentId => $figureKeys) {
                    $parentTreeEntries[$structParentId] = array_map(
                        static fn (string $key): int => $figureStructElemObjectIds[$key],
                        $figureKeys,
                    );
                }

                $objects[] = new IndirectObject($parentTreeObjectId, (new ParentTree($parentTreeEntries))->objectContents());
            }

            foreach ($taggedImageStructure['figureEntries'] as $figureEntry) {
                $objects[] = new IndirectObject(
                    $figureStructElemObjectIds[$figureEntry['key']],
                    (new StructElem(
                        'Figure',
                        $documentStructElemObjectId,
                        pageObjectId: $pageObjectIds[$figureEntry['pageIndex']],
                        altText: $figureEntry['altText'],
                        markedContentId: $figureEntry['markedContentId'],
                    ))->objectContents(),
                );
            }
        }

        if ($metadataObjectId !== null) {
            $objects[] = new IndirectObject($metadataObjectId, (new XmpMetadata())->objectContents($document, $serializedAt));
        }

        if ($iccProfileObjectId !== null) {
            $outputIntent = $this->resolvePdfAOutputIntent($document);
            $objects[] = new IndirectObject(
                $iccProfileObjectId,
                IccProfile::fromPath($outputIntent->iccProfilePath, $outputIntent->colorComponents)->objectContents(),
            );
        }

        if ($infoObjectId !== null) {
            $objects[] = new IndirectObject($infoObjectId, $this->buildInfoDictionary($document, $serializedAt));
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

    private function assertProfileRequirements(Document $document): void
    {
        if ($document->profile->requiresDocumentLanguage() && $document->language === null) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires a document language.',
                $document->profile->name(),
            ));
        }

        if ($document->profile->requiresDocumentTitle() && $document->title === null) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires a document title.',
                $document->profile->name(),
            ));
        }
    }

    private function assertImageAccessibilityRequirements(Document $document): void
    {
        if (!$document->profile->requiresTaggedImages()) {
            return;
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->images as $imageIndex => $pageImage) {
                $accessibility = $pageImage->accessibility;

                if ($accessibility === null) {
                    throw new InvalidArgumentException(sprintf(
                        'Tagged PDF profiles require accessibility metadata for image %d on page %d.',
                        $imageIndex + 1,
                        $pageIndex + 1,
                    ));
                }

                if (
                    $document->profile->requiresFigureAltText()
                    && $accessibility->requiresFigureTag()
                    && $accessibility->altText === null
                ) {
                    throw new InvalidArgumentException(sprintf(
                        'Tagged PDF profiles require alternative text for image %d on page %d.',
                        $imageIndex + 1,
                        $pageIndex + 1,
                    ));
                }
            }
        }
    }

    private function hasInfoMetadata(Document $document): bool
    {
        return $document->title !== null
            || $document->author !== null
            || $document->subject !== null
            || $document->creator !== null
            || $document->creatorTool !== null;
    }

    private function usesMetadataStream(Document $document): bool
    {
        if (!$document->profile->supportsXmpMetadata()) {
            return false;
        }

        return $this->hasInfoMetadata($document)
            || $document->language !== null
            || $document->profile->requiresTaggedPdf()
            || $document->profile->writesPdfAIdentificationMetadata()
            || $document->profile->writesPdfUaIdentificationMetadata();
    }

    private function buildInfoDictionary(Document $document, DateTimeImmutable $serializedAt): string
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

        $pdfDate = $this->pdfDate($serializedAt);
        $entries[] = '/CreationDate ' . $this->pdfString($pdfDate);
        $entries[] = '/ModDate ' . $this->pdfString($pdfDate);

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function buildCatalogDictionary(
        Document $document,
        ?int $metadataObjectId,
        ?int $iccProfileObjectId,
        ?int $structTreeRootObjectId,
    ): string
    {
        $entries = [
            '/Type /Catalog',
            '/Pages 2 0 R',
        ];

        if ($metadataObjectId !== null) {
            $entries[] = '/Metadata ' . $metadataObjectId . ' 0 R';
        }

        if ($document->language !== null) {
            $entries[] = '/Lang ' . $this->pdfString($document->language);
        }

        if ($document->profile->requiresTaggedPdf()) {
            $entries[] = '/MarkInfo << /Marked true >>';
        }

        if ($iccProfileObjectId !== null) {
            $outputIntent = $this->resolvePdfAOutputIntent($document);
            $entries[] = '/OutputIntents [<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier '
                . $this->pdfString($outputIntent->outputConditionIdentifier)
                . ($outputIntent->info !== null ? ' /Info ' . $this->pdfString($outputIntent->info) : '')
                . ' /DestOutputProfile '
                . $iccProfileObjectId
                . ' 0 R >>]';
        }

        if ($structTreeRootObjectId !== null) {
            $entries[] = '/StructTreeRoot ' . $structTreeRootObjectId . ' 0 R';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function buildStructParentsEntry(?int $structParentId): string
    {
        if ($structParentId === null) {
            return '';
        }

        return ' /StructParents ' . $structParentId;
    }

    /**
     * @return array{
     *   figureEntries: list<array{key: string, pageIndex: int, markedContentId: int, altText: ?string}>,
     *   parentTreeEntries: array<int, list<string>>,
     *   pageStructParentIds: array<int, int>
     * }
     */
    private function collectTaggedImageStructure(Document $document): array
    {
        $figureEntries = [];
        $parentTreeEntries = [];
        $pageStructParentIds = [];
        $nextStructParentId = 0;

        foreach ($document->pages as $pageIndex => $page) {
            $pageFigureKeys = [];

            foreach ($page->images as $imageIndex => $pageImage) {
                if ($pageImage->markedContentId === null) {
                    continue;
                }

                $key = $pageIndex . ':' . $imageIndex;
                $pageFigureKeys[$pageImage->markedContentId] = $key;
                $figureEntries[] = [
                    'key' => $key,
                    'pageIndex' => $pageIndex,
                    'markedContentId' => $pageImage->markedContentId,
                    'altText' => $pageImage->accessibility?->altText,
                ];
            }

            if ($pageFigureKeys === []) {
                continue;
            }

            ksort($pageFigureKeys);
            $pageStructParentIds[$pageIndex] = $nextStructParentId;
            $parentTreeEntries[$nextStructParentId] = array_values($pageFigureKeys);
            $nextStructParentId++;
        }

        return [
            'figureEntries' => $figureEntries,
            'parentTreeEntries' => $parentTreeEntries,
            'pageStructParentIds' => $pageStructParentIds,
        ];
    }

    private function resolvePdfAOutputIntent(Document $document): PdfAOutputIntent
    {
        return $document->pdfaOutputIntent ?? PdfAOutputIntent::defaultSrgb();
    }

    private function assertPdfARequirements(Document $document): void
    {
        if (!$document->profile->isPdfA()) {
            return;
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->fontResources as $fontIndex => $pageFont) {
                if ($pageFont->isEmbedded()) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Profile %s requires embedded fonts. Found standard font "%s" on page %d.',
                    $document->profile->name(),
                    $pageFont->name,
                    $pageIndex + 1,
                ));
            }

            $imageResourceIndex = 0;

            foreach ($page->imageResources as $imageSource) {
                $imageResourceIndex++;

                if ($imageSource->softMask === null || $document->profile->supportsCurrentTransparencyImplementation()) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow soft-mask image transparency for image resource %d on page %d.',
                    $document->profile->name(),
                    $imageResourceIndex,
                    $pageIndex + 1,
                ));
            }
        }
    }

    private function pdfDate(DateTimeImmutable $timestamp): string
    {
        return $timestamp->format("YmdHisO");
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
     * @param array<string, ImageSource> $imageResources
     * @param array<string, int> $fontObjectIds
     * @param array<string, int> $imageObjectIds
     */
    private function buildPageResources(array $fontResources, array $imageResources, array $fontObjectIds, array $imageObjectIds): string
    {
        if ($fontResources === [] && $imageResources === []) {
            return '<< >>';
        }

        $entries = [];

        foreach ($fontResources as $fontAlias => $pageFont) {
            $entries[] = '/' . $fontAlias . ' ' . $fontObjectIds[$this->fontObjectKey($pageFont)] . ' 0 R';
        }

        $resourceEntries = [];

        if ($entries !== []) {
            $resourceEntries[] = '/Font << ' . implode(' ', $entries) . ' >>';
        }

        $imageEntries = [];

        foreach ($imageResources as $imageAlias => $imageSource) {
            $imageEntries[] = '/' . $imageAlias . ' ' . $imageObjectIds[$imageSource->key()] . ' 0 R';
        }

        if ($imageEntries !== []) {
            $resourceEntries[] = '/XObject << ' . implode(' ', $imageEntries) . ' >>';
        }

        return '<< ' . implode(' ', $resourceEntries) . ' >>';
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
                    /** @var list<EmbeddedGlyph> $embeddedGlyphs */
                    $embeddedGlyphs = $pageFont->embeddedGlyphs;
                    $fonts[$fontKey] = $fonts[$fontKey]->withAdditionalEmbeddedGlyphs($embeddedGlyphs);
                }
            }
        }

        return $fonts;
    }

    /**
     * @param array<string, int> $imageObjectIds
     */
    private function reserveImageObjectIds(ImageSource $imageSource, array &$imageObjectIds, int $nextObjectId): int
    {
        $imageKey = $imageSource->key();

        if (!isset($imageObjectIds[$imageKey])) {
            $imageObjectIds[$imageKey] = $nextObjectId;
            $nextObjectId++;
        }

        if ($imageSource->softMask !== null) {
            $nextObjectId = $this->reserveImageObjectIds($imageSource->softMask, $imageObjectIds, $nextObjectId);
        }

        return $nextObjectId;
    }

    /**
     * @param list<Page> $pages
     * @return array<string, ImageSource>
     */
    private function collectImages(array $pages): array
    {
        $images = [];

        foreach ($pages as $page) {
            foreach ($page->imageResources as $imageSource) {
                $this->collectImageSource($imageSource, $images);
            }
        }

        return $images;
    }

    /**
     * @param array<string, ImageSource> $images
     */
    private function collectImageSource(ImageSource $imageSource, array &$images): void
    {
        $imageKey = $imageSource->key();

        if (!isset($images[$imageKey])) {
            $images[$imageKey] = $imageSource;
        }

        if ($imageSource->softMask !== null) {
            $this->collectImageSource($imageSource->softMask, $images);
        }
    }
}
