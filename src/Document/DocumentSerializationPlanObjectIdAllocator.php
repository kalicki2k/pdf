<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_values;
use function count;

use Kalle\Pdf\Document\TaggedPdf\TaggedStructureCollector;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureObjectIds;
use Kalle\Pdf\Font\OpenTypeOutlineType;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\RelatedObjectsPageAnnotation;

final readonly class DocumentSerializationPlanObjectIdAllocator
{
    public function __construct(
        private DocumentMetadataInspector $metadataInspector = new DocumentMetadataInspector(),
        private TaggedStructureCollector $taggedStructureCollector = new TaggedStructureCollector(),
        private PdfAAnnotationAppearancePolicy $pdfAAnnotationAppearancePolicy = new PdfAAnnotationAppearancePolicy(),
        private PdfAFormContextFactory $pdfAFormContextFactory = new PdfAFormContextFactory(),
    ) {
    }

    public function allocate(
        Document $document,
        callable $collectTaggedLinkStructure,
        callable $collectTaggedPageAnnotations,
        callable $collectTaggedFormStructure,
        callable $collectNamedDestinations,
    ): DocumentSerializationPlanBuildState {
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
        /** @var array<string, int> $cidSetObjectIds */
        $cidSetObjectIds = [];
        /** @var array<string, int> $imageObjectIds */
        $imageObjectIds = [];
        /** @var array<int, list<int>> $pageAnnotationObjectIds */
        $pageAnnotationObjectIds = [];
        /** @var array<int, list<?int>> $pageAnnotationAppearanceObjectIds */
        $pageAnnotationAppearanceObjectIds = [];
        /** @var array<int, array<int, list<int>>> $pageAnnotationRelatedObjectIds */
        $pageAnnotationRelatedObjectIds = [];
        /** @var list<int> $attachmentObjectIds */
        $attachmentObjectIds = [];
        /** @var list<int> $embeddedFileObjectIds */
        $embeddedFileObjectIds = [];
        $acroFormObjectId = null;
        /** @var list<int> $acroFormFieldObjectIds */
        $acroFormFieldObjectIds = [];
        /** @var array<int, list<int>> $acroFormFieldRelatedObjectIds */
        $acroFormFieldRelatedObjectIds = [];
        /** @var array<int, list<int>> $pageFormWidgetObjectIds */
        $pageFormWidgetObjectIds = [];
        $acroFormDefaultFont = null;
        $acroFormDefaultFontKey = null;

        foreach ($document->pages as $page) {
            $pageObjectIds[] = $nextObjectId++;
            $contentObjectIds[] = $nextObjectId++;
        }

        foreach ($document->pages as $pageIndex => $_page) {
            $pageFormWidgetObjectIds[$pageIndex] = [];
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->fontResources as $pageFont) {
                $nextObjectId = $this->reserveFontObjectIds(
                    $pageFont,
                    $fontObjectIds,
                    $fontDescriptorObjectIds,
                    $fontFileObjectIds,
                    $cidFontObjectIds,
                    $toUnicodeObjectIds,
                    $cidToGidMapObjectIds,
                    $cidSetObjectIds,
                    $nextObjectId,
                );
            }

            foreach ($page->imageResources as $imageSource) {
                $nextObjectId = $this->reserveImageObjectIds($imageSource, $imageObjectIds, $nextObjectId);
            }

            $pageAnnotationObjectIds[$pageIndex] = [];
            $pageAnnotationAppearanceObjectIds[$pageIndex] = [];
            $pageAnnotationRelatedObjectIds[$pageIndex] = [];

            foreach ($page->annotations as $annotationIndex => $annotation) {
                $pageAnnotationObjectIds[$pageIndex][] = $nextObjectId++;
                $pageAnnotationAppearanceObjectIds[$pageIndex][] = $this->pdfAAnnotationAppearancePolicy->requiresAppearanceStream($document, $annotation)
                    ? $nextObjectId++
                    : null;
                $pageAnnotationRelatedObjectIds[$pageIndex][$annotationIndex] = [];

                if ($annotation instanceof RelatedObjectsPageAnnotation) {
                    for ($relatedObjectIndex = 0; $relatedObjectIndex < $annotation->relatedObjectCount(); $relatedObjectIndex++) {
                        $pageAnnotationRelatedObjectIds[$pageIndex][$annotationIndex][] = $nextObjectId++;
                    }
                }
            }
        }

        foreach ($document->attachments as $attachment) {
            $embeddedFileObjectIds[] = $nextObjectId++;
            $attachmentObjectIds[] = $nextObjectId++;
        }

        if ($document->acroForm !== null) {
            $acroFormDefaultFont = $this->pdfAFormContextFactory->buildDefaultFont($document);

            if ($acroFormDefaultFont !== null) {
                $acroFormDefaultFontKey = $acroFormDefaultFont->key();
                $nextObjectId = $this->reserveFontObjectIds(
                    $acroFormDefaultFont,
                    $fontObjectIds,
                    $fontDescriptorObjectIds,
                    $fontFileObjectIds,
                    $cidFontObjectIds,
                    $toUnicodeObjectIds,
                    $cidToGidMapObjectIds,
                    $cidSetObjectIds,
                    $nextObjectId,
                );
            }

            $acroFormObjectId = $nextObjectId++;

            foreach ($document->acroForm->fields as $fieldIndex => $field) {
                $fieldObjectId = $nextObjectId++;
                $acroFormFieldObjectIds[$fieldIndex] = $fieldObjectId;
                $acroFormFieldRelatedObjectIds[$fieldIndex] = [];

                for ($relatedObjectIndex = 0; $relatedObjectIndex < $field->relatedObjectCount(); $relatedObjectIndex++) {
                    $acroFormFieldRelatedObjectIds[$fieldIndex][] = $nextObjectId++;
                }

                foreach ($field->pageAnnotationObjectIds($fieldObjectId, $acroFormFieldRelatedObjectIds[$fieldIndex]) as $pageNumber => $annotationObjectIds) {
                    $pageIndex = $pageNumber - 1;
                    $pageFormWidgetObjectIds[$pageIndex] = [
                        ...($pageFormWidgetObjectIds[$pageIndex] ?? []),
                        ...$annotationObjectIds,
                    ];
                }
            }
        }

        $taggedStructure = $this->taggedStructureCollector->collect($document);
        $pageStructParentIds = $this->assignPageStructParentIds($taggedStructure->pageMarkedContentKeys);
        /** @var array{
         *   linkEntries: list<array{
         *     key: string,
         *     pageIndex: int,
         *     annotationIndices: list<int>,
         *     altText: string,
         *     markedContentIds: list<int>
         *   }>,
         *   structParentIds: array<string, int>,
         *   parentTreeEntries: array<int, list<string>>,
         *   nextStructParentId: int
         * } $taggedLinkStructure */
        $taggedLinkStructure = $collectTaggedLinkStructure(count($pageStructParentIds));
        /** @var array{
         *   entries: list<array{
         *     key: string,
         *     pageIndex: int,
         *     annotationIndex: int,
         *     altText: string,
         *     tag: string
         *   }>,
         *   structParentIds: array<string, int>,
         *   parentTreeEntries: array<int, list<string>>,
         *   nextStructParentId: int
         * } $taggedPageAnnotationStructure */
        $taggedPageAnnotationStructure = $collectTaggedPageAnnotations(
            $taggedLinkStructure['nextStructParentId'],
        );
        /** @var array{
         *   entries: list<array{
         *     key: string,
         *     annotationObjectId: int,
         *     pageIndex: int,
         *     altText: string
         *   }>,
         *   structParentIds: array<int, int>,
         *   parentTreeEntries: array<int, list<string>>
         * } $taggedFormStructure */
        $taggedFormStructure = $collectTaggedFormStructure(
            array_values($acroFormFieldObjectIds),
            $acroFormFieldRelatedObjectIds,
            $taggedPageAnnotationStructure['nextStructParentId'],
        );
        /** @var array<string, string> $namedDestinations */
        $namedDestinations = $collectNamedDestinations();
        $outlineRootObjectId = null;
        /** @var list<int> $outlineItemObjectIds */
        $outlineItemObjectIds = [];

        if ($document->outlines !== []) {
            $outlineRootObjectId = $nextObjectId++;

            foreach ($document->outlines as $_outline) {
                $outlineItemObjectIds[] = $nextObjectId++;
            }
        }

        $structTreeRootObjectId = $document->profile->requiresTaggedPdf() ? $nextObjectId++ : null;
        $documentStructElemObjectId = $document->profile->requiresTaggedPdf() ? $nextObjectId++ : null;
        $parentTreeObjectId = ($taggedStructure->pageMarkedContentKeys !== []
            || $taggedLinkStructure['parentTreeEntries'] !== []
            || $taggedPageAnnotationStructure['parentTreeEntries'] !== []
            || $taggedFormStructure['parentTreeEntries'] !== [])
            ? $nextObjectId++
            : null;
        $taggedStructureObjectIds = TaggedStructureObjectIds::allocate(
            $document,
            $taggedStructure,
            $taggedLinkStructure['linkEntries'],
            $taggedPageAnnotationStructure['entries'],
            $nextObjectId,
        );
        $nextObjectId = $taggedStructureObjectIds->nextObjectId;
        $taggedFormStructElemObjectIds = [];

        foreach ($taggedFormStructure['entries'] as $formEntry) {
            $taggedFormStructElemObjectIds[$formEntry['key']] = $nextObjectId++;
        }

        $metadataObjectId = $this->metadataInspector->usesMetadataStream($document) ? $nextObjectId++ : null;
        $iccProfileObjectId = $document->profile->usesPdfAOutputIntent() ? $nextObjectId++ : null;
        $infoObjectId = $document->profile->writesInfoDictionary() && $this->metadataInspector->hasInfoMetadata($document)
            ? $nextObjectId++
            : null;
        $encryptObjectId = $document->encryption !== null ? $nextObjectId++ : null;

        return new DocumentSerializationPlanBuildState(
            $pageObjectIds,
            $contentObjectIds,
            $fontObjectIds,
            $fontDescriptorObjectIds,
            $fontFileObjectIds,
            $cidFontObjectIds,
            $toUnicodeObjectIds,
            $cidToGidMapObjectIds,
            $cidSetObjectIds,
            $imageObjectIds,
            $pageAnnotationObjectIds,
            $pageAnnotationAppearanceObjectIds,
            $pageAnnotationRelatedObjectIds,
            $attachmentObjectIds,
            $embeddedFileObjectIds,
            $acroFormObjectId,
            array_values($acroFormFieldObjectIds),
            $acroFormFieldRelatedObjectIds,
            $pageFormWidgetObjectIds,
            $taggedStructure,
            $pageStructParentIds,
            $taggedLinkStructure,
            $taggedPageAnnotationStructure,
            $taggedFormStructure,
            $namedDestinations,
            $outlineRootObjectId,
            $outlineItemObjectIds,
            $structTreeRootObjectId,
            $documentStructElemObjectId,
            $parentTreeObjectId,
            $taggedStructureObjectIds,
            $taggedFormStructElemObjectIds,
            $metadataObjectId,
            $iccProfileObjectId,
            $infoObjectId,
            $encryptObjectId,
            $acroFormDefaultFont,
            $acroFormDefaultFontKey,
        );
    }

    /**
     * @param array<string, int> $fontObjectIds
     * @param array<string, int> $fontDescriptorObjectIds
     * @param array<string, int> $fontFileObjectIds
     * @param array<string, int> $cidFontObjectIds
     * @param array<string, int> $toUnicodeObjectIds
     * @param array<string, int> $cidToGidMapObjectIds
     * @param array<string, int> $cidSetObjectIds
     */
    private function reserveFontObjectIds(
        PageFont $pageFont,
        array &$fontObjectIds,
        array &$fontDescriptorObjectIds,
        array &$fontFileObjectIds,
        array &$cidFontObjectIds,
        array &$toUnicodeObjectIds,
        array &$cidToGidMapObjectIds,
        array &$cidSetObjectIds,
        int $nextObjectId,
    ): int {
        $fontKey = $pageFont->key();

        if (isset($fontObjectIds[$fontKey])) {
            return $nextObjectId;
        }

        $fontObjectIds[$fontKey] = $nextObjectId++;

        if (!$pageFont->isEmbedded()) {
            return $nextObjectId;
        }

        if ($pageFont->usesUnicodeCids()) {
            $embeddedFont = $pageFont->embeddedDefinition();
            $cidFontObjectIds[$fontKey] = $nextObjectId++;
            $fontDescriptorObjectIds[$fontKey] = $nextObjectId++;
            $fontFileObjectIds[$fontKey] = $nextObjectId++;
            $toUnicodeObjectIds[$fontKey] = $nextObjectId++;
            $cidSetObjectIds[$fontKey] = $nextObjectId++;

            if ($embeddedFont->metadata->outlineType === OpenTypeOutlineType::TRUE_TYPE) {
                $cidToGidMapObjectIds[$fontKey] = $nextObjectId++;
            }

            return $nextObjectId;
        }

        $fontDescriptorObjectIds[$fontKey] = $nextObjectId++;
        $fontFileObjectIds[$fontKey] = $nextObjectId++;

        return $nextObjectId;
    }

    /**
     * @param array<string, int> $imageObjectIds
     */
    private function reserveImageObjectIds(ImageSource $imageSource, array &$imageObjectIds, int $nextObjectId): int
    {
        $imageKey = $imageSource->key();

        if (!isset($imageObjectIds[$imageKey])) {
            $imageObjectIds[$imageKey] = $nextObjectId++;
        }

        if ($imageSource->softMask !== null) {
            $nextObjectId = $this->reserveImageObjectIds($imageSource->softMask, $imageObjectIds, $nextObjectId);
        }

        return $nextObjectId;
    }

    /**
     * @param array<int, array<int, string>> $pageMarkedContentKeys
     * @return array<int, int>
     */
    private function assignPageStructParentIds(array $pageMarkedContentKeys): array
    {
        $pageStructParentIds = [];
        $nextStructParentId = 0;
        ksort($pageMarkedContentKeys);

        foreach ($pageMarkedContentKeys as $pageIndex => $pageKeys) {
            if ($pageKeys === []) {
                continue;
            }

            $pageStructParentIds[$pageIndex] = $nextStructParentId++;
        }

        return $pageStructParentIds;
    }
}
