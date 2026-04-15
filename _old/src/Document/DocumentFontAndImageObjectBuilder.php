<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Writer\IndirectObject;

final class DocumentFontAndImageObjectBuilder
{
    /**
     * @return list<IndirectObject>
     */
    public function buildObjects(Document $document, DocumentSerializationPlanBuildState $state): array
    {
        $objects = [];

        $fonts = $this->collectFonts($document->pages);

        if ($state->acroFormDefaultFont !== null && $state->acroFormDefaultFontKey !== null) {
            $fonts[$state->acroFormDefaultFontKey] = $state->acroFormDefaultFont;
        }

        foreach ($fonts as $fontKey => $pageFont) {
            $fontObjectId = $state->fontObjectIds[$fontKey];

            if ($pageFont->isEmbedded()) {
                $embeddedFont = $pageFont->embeddedDefinition();
                $fontDescriptorObjectId = $state->fontDescriptorObjectIds[$fontKey];
                $fontFileObjectId = $state->fontFileObjectIds[$fontKey];

                if ($pageFont->usesUnicodeCids()) {
                    /** @var list<EmbeddedGlyph> $embeddedGlyphs */
                    $embeddedGlyphs = $pageFont->embeddedGlyphs;
                    $cidFontObjectId = $state->cidFontObjectIds[$fontKey];
                    $toUnicodeObjectId = $state->toUnicodeObjectIds[$fontKey];
                    $cidToGidMapObjectId = $state->cidToGidMapObjectIds[$fontKey] ?? null;
                    $cidSetObjectId = $state->cidSetObjectIds[$fontKey] ?? null;
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
                        $embeddedFont->fontDescriptorContentsWithCidSet($fontFileObjectId, $subsetFontName, $cidSetObjectId),
                    );
                    $objects[] = IndirectObject::stream(
                        $fontFileObjectId,
                        $embeddedFont->unicodeSubsetFontFileStreamDictionaryContentsForGlyphs($embeddedGlyphs),
                        $embeddedFont->unicodeSubsetFontFileStreamDataForGlyphs($embeddedGlyphs),
                    );
                    $objects[] = IndirectObject::stream(
                        $toUnicodeObjectId,
                        $embeddedFont->unicodeToUnicodeStreamDictionaryContentsForGlyphs($embeddedGlyphs),
                        $embeddedFont->unicodeToUnicodeStreamDataForGlyphs($embeddedGlyphs),
                    );

                    if ($cidToGidMapObjectId !== null) {
                        $objects[] = IndirectObject::stream(
                            $cidToGidMapObjectId,
                            $embeddedFont->unicodeCidToGidMapStreamDictionaryContentsForGlyphs($embeddedGlyphs),
                            $embeddedFont->unicodeCidToGidMapStreamDataForGlyphs($embeddedGlyphs),
                        );
                    }

                    if ($cidSetObjectId !== null) {
                        $objects[] = IndirectObject::stream(
                            $cidSetObjectId,
                            $embeddedFont->unicodeCidSetStreamDictionaryContentsForGlyphs($embeddedGlyphs),
                            $embeddedFont->unicodeCidSetStreamDataForGlyphs($embeddedGlyphs),
                        );
                    }

                    continue;
                }

                $objects[] = new IndirectObject($fontObjectId, $embeddedFont->fontObjectContents($fontDescriptorObjectId));
                $objects[] = new IndirectObject($fontDescriptorObjectId, $embeddedFont->fontDescriptorContents($fontFileObjectId));
                $objects[] = IndirectObject::stream(
                    $fontFileObjectId,
                    $embeddedFont->fontFileStreamDictionaryContents(),
                    $embeddedFont->fontFileStreamData(),
                );

                continue;
            }

            $objects[] = new IndirectObject($fontObjectId, $pageFont->pdfObjectContents());
        }

        foreach ($this->collectImages($document->pages) as $imageKey => $imageSource) {
            $objects[] = IndirectObject::stream(
                $state->imageObjectIds[$imageKey],
                $imageSource->pdfObjectDictionaryContents(
                    $imageSource->softMask !== null ? $state->imageObjectIds[$imageSource->softMask->key()] : null,
                ),
                $imageSource->pdfObjectStreamContents(),
            );
        }

        return $objects;
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
                $fontKey = $pageFont->key();

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
