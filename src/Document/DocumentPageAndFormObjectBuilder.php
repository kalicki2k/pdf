<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_map;
use function count;
use function implode;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Document\Form\FormFieldRenderContext;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\AnnotationAppearanceRenderContext;
use Kalle\Pdf\Page\AppearanceStreamAnnotation;
use Kalle\Pdf\Page\OptionalContentGroup;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\RelatedObjectsPageAnnotation;
use Kalle\Pdf\Writer\IndirectObject;

final class DocumentPageAndFormObjectBuilder
{
    /**
     * @return list<IndirectObject>
     */
    public function buildPageObjects(Document $document, DocumentSerializationPlanBuildState $state, ?Debugger $debugger = null): array
    {
        $debugger ??= Debugger::disabled();
        $objects = [];
        $pageObjectIdsByPageNumber = $this->pageObjectIdsByPageNumber($state->pageObjectIds);
        $attachmentObjectIdsByFilename = $this->attachmentObjectIdsByFilename($document, $state->attachmentObjectIds);
        $printableAnnotations = $document->profile->requiresPrintableAnnotations();

        foreach ($document->pages as $index => $page) {
            $scope = $debugger->startPerformanceScope('page.render', [
                'page' => $index + 1,
                'page_count' => count($document->pages),
            ]);
            $pageObjectId = $state->pageObjectIds[$index];
            $contentObjectId = $state->contentObjectIds[$index];
            $annotationObjectIds = $state->pageAnnotationObjectIds[$index] ?? [];
            $formWidgetObjectIds = $state->pageFormWidgetObjectIds[$index] ?? [];
            $allAnnotationObjectIds = [...$annotationObjectIds, ...$formWidgetObjectIds];
            $annotationAppearanceContext = new AnnotationAppearanceRenderContext(
                $this->pageFontObjectIdsByAlias($page->fontResources, $state->fontObjectIds),
            );
            $pageContents = $this->buildPageContents($document, $page);

            $objects[] = IndirectObject::plain(
                $pageObjectId,
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $this->formatNumber($page->size->width()) . ' '
                . $this->formatNumber($page->size->height()) . '] /Resources '
                . $this->buildPageResources(
                    $page->fontResources,
                    $page->imageResources,
                    $page->optionalContentGroups,
                    $state->fontObjectIds,
                    $state->imageObjectIds,
                    $state->optionalContentGroupObjectIds,
                ) . ' /Contents '
                . $contentObjectId . ' 0 R'
                . $this->buildPageAnnotationsEntry($allAnnotationObjectIds)
                . $this->buildAnnotationTabOrderEntry($document, $allAnnotationObjectIds)
                . $this->buildStructParentsEntry($state->pageStructParentIds[$index] ?? null)
                . ' >>',
            );
            $objects[] = IndirectObject::stream(
                $contentObjectId,
                $this->buildContentStreamDictionary($pageContents),
                $this->buildContentStreamContents($pageContents),
            );

            foreach ($page->annotations as $annotationIndex => $annotation) {
                $annotationKey = $index . ':' . $annotationIndex;
                $annotationContext = $this->pageAnnotationRenderContext(
                    $state,
                    $pageObjectId,
                    $annotationKey,
                    $annotationObjectIds[$annotationIndex],
                    $appearanceObjectId = $state->pageAnnotationAppearanceObjectIds[$index][$annotationIndex] ?? null,
                    $state->pageAnnotationRelatedObjectIds[$index][$annotationIndex] ?? [],
                    $pageObjectIdsByPageNumber,
                    $attachmentObjectIdsByFilename,
                    $printableAnnotations,
                );

                $objects[] = IndirectObject::plain(
                    $annotationObjectIds[$annotationIndex],
                    $annotation->pdfObjectContents($annotationContext),
                );

                if ($appearanceObjectId !== null && $annotation instanceof AppearanceStreamAnnotation) {
                    $objects[] = IndirectObject::stream(
                        $appearanceObjectId,
                        $annotation->appearanceStreamDictionaryContents($annotationAppearanceContext),
                        $annotation->appearanceStreamContents($annotationAppearanceContext),
                    );
                }

                if ($annotation instanceof RelatedObjectsPageAnnotation) {
                    $this->appendObjects(
                        $objects,
                        $annotation->relatedObjects($annotationContext),
                    );
                }
            }

            $scope->stop([
                'page' => $index + 1,
                'page_object_id' => $pageObjectId,
                'contents_id' => $contentObjectId,
            ]);
        }

        return $objects;
    }

    /**
     * @param list<int> $attachmentObjectIds
     * @return array<string, int>
     */
    private function attachmentObjectIdsByFilename(Document $document, array $attachmentObjectIds): array
    {
        $objectIds = [];

        foreach ($document->attachments as $index => $attachment) {
            $objectIds[$attachment->filename] = $attachmentObjectIds[$index];
        }

        return $objectIds;
    }

    /**
     * @return list<IndirectObject>
     */
    public function buildAcroFormObjects(Document $document, DocumentSerializationPlanBuildState $state): array
    {
        if ($document->acroForm === null) {
            return [];
        }

        $acroFormObjectId = $state->acroFormObjectId;

        if ($acroFormObjectId === null) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'AcroForm object ID allocation is missing.',
            );
        }

        $acroForm = $document->profile->isPdfA()
            ? $document->acroForm->withNeedAppearances(false)
            : $document->acroForm;

        $objects = [
            IndirectObject::plain(
                $acroFormObjectId,
                $acroForm->pdfObjectContents(
                    $state->acroFormFieldObjectIds,
                    $state->acroFormDefaultFontKey !== null ? ($state->fontObjectIds[$state->acroFormDefaultFontKey] ?? null) : null,
                    allowBuiltinDefaultTextFontFallback: !$document->profile->isPdfA(),
                ),
            ),
        ];
        $pageObjectIdsByPageNumber = $this->pageObjectIdsByPageNumber($state->pageObjectIds);
        $defaultFontObjectId = $state->acroFormDefaultFontKey !== null ? ($state->fontObjectIds[$state->acroFormDefaultFontKey] ?? null) : null;
        $context = new FormFieldRenderContext(
            $pageObjectIdsByPageNumber,
            $state->taggedFormStructure['structParentIds'],
            $state->acroFormDefaultFont,
            $state->acroFormDefaultFont !== null ? 'F0' : null,
            $defaultFontObjectId,
        );

        foreach ($document->acroForm->fields as $fieldIndex => $field) {
            $objects[] = IndirectObject::plain(
                $state->acroFormFieldObjectIds[$fieldIndex],
                $field->pdfObjectContents(
                    $context,
                    $state->acroFormFieldObjectIds[$fieldIndex],
                    $state->acroFormFieldRelatedObjectIds[$fieldIndex] ?? [],
                ),
            );

            foreach ($field->relatedObjects(
                $context,
                $state->acroFormFieldObjectIds[$fieldIndex],
                $state->acroFormFieldRelatedObjectIds[$fieldIndex] ?? [],
            ) as $relatedObject) {
                $objects[] = $relatedObject;
            }
        }

        return $objects;
    }

    /**
     * @param list<int> $relatedObjectIds
     * @param array<int, int> $pageObjectIdsByPageNumber
     * @param array<string, int> $attachmentObjectIdsByFilename
     */
    private function pageAnnotationRenderContext(
        DocumentSerializationPlanBuildState $state,
        int $pageObjectId,
        string $annotationKey,
        int $annotationObjectId,
        ?int $appearanceObjectId,
        array $relatedObjectIds,
        array $pageObjectIdsByPageNumber,
        array $attachmentObjectIdsByFilename,
        bool $printableAnnotations,
    ): PageAnnotationRenderContext {
        return new PageAnnotationRenderContext(
            pageObjectId: $pageObjectId,
            printable: $printableAnnotations,
            pageObjectIdsByPageNumber: $pageObjectIdsByPageNumber,
            namedDestinations: $state->namedDestinations,
            structParentId: $state->taggedLinkStructure['structParentIds'][$annotationKey]
                ?? $state->taggedPageAnnotationStructure['structParentIds'][$annotationKey]
                ?? null,
            appearanceObjectId: $appearanceObjectId,
            annotationObjectId: $annotationObjectId,
            relatedObjectIds: $relatedObjectIds,
            attachmentObjectIdsByFilename: $attachmentObjectIdsByFilename,
        );
    }

    private function buildStructParentsEntry(?int $structParentId): string
    {
        if ($structParentId === null) {
            return '';
        }

        return ' /StructParents ' . $structParentId;
    }

    /**
     * @param list<int> $annotationObjectIds
     */
    private function buildPageAnnotationsEntry(array $annotationObjectIds): string
    {
        if ($annotationObjectIds === []) {
            return '';
        }

        return ' /Annots [' . implode(' ', array_map(
            static fn (int $objectId): string => $objectId . ' 0 R',
            $annotationObjectIds,
        )) . ']';
    }

    /**
     * @param list<int> $annotationObjectIds
     */
    private function buildAnnotationTabOrderEntry(Document $document, array $annotationObjectIds): string
    {
        if ($annotationObjectIds === [] || !$document->profile->requiresPageAnnotationTabOrder()) {
            return '';
        }

        return ' /Tabs /S';
    }

    private function buildContentStreamDictionary(string $contents): string
    {
        $normalizedContents = $contents;

        if ($normalizedContents !== '' && !str_ends_with($normalizedContents, "\n")) {
            $normalizedContents .= "\n";
        }

        return '<< /Length ' . strlen($normalizedContents) . ' >>';
    }

    private function buildContentStreamContents(string $contents): string
    {
        if ($contents !== '' && !str_ends_with($contents, "\n")) {
            return $contents . "\n";
        }

        return $contents;
    }

    private function buildPageContents(Document $document, Page $page): string
    {
        $contents = $page->contents;

        if ($page->backgroundColor === null) {
            return $contents;
        }

        $backgroundContents = $this->buildBackgroundContents($document, $page);

        if ($contents === '') {
            return $backgroundContents;
        }

        return $backgroundContents . "\n" . $contents;
    }

    private function buildBackgroundContents(Document $document, Page $page): string
    {
        $color = $page->backgroundColor;

        if ($color === null) {
            return '';
        }

        $contents = implode("\n", [
            'q',
            $this->buildFillColorOperator($color),
            '0 0 ' . $this->formatNumber($page->size->width()) . ' ' . $this->formatNumber($page->size->height()) . ' re',
            'f',
            'Q',
        ]);

        // Page backgrounds are presentation-only and must stay outside the tagged content tree.
        return $this->wrapArtifactContents($document, $contents);
    }

    private function wrapArtifactContents(Document $document, string $contents): string
    {
        if ($contents === '' || !$document->profile->requiresTaggedPdf()) {
            return $contents;
        }

        return implode("\n", [
            '/Artifact BMC',
            $contents,
            'EMC',
        ]);
    }

    private function buildFillColorOperator(Color $color): string
    {
        $components = array_map(
            $this->formatNumber(...),
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
     * @param array<string, OptionalContentGroup> $optionalContentGroups
     * @param array<string, int> $fontObjectIds
     * @param array<string, int> $imageObjectIds
     * @param array<string, int> $optionalContentGroupObjectIds
     */
    private function buildPageResources(
        array $fontResources,
        array $imageResources,
        array $optionalContentGroups,
        array $fontObjectIds,
        array $imageObjectIds,
        array $optionalContentGroupObjectIds,
    ): string {
        if ($fontResources === [] && $imageResources === [] && $optionalContentGroups === []) {
            return '<< >>';
        }

        $entries = [];

        foreach ($fontResources as $fontAlias => $pageFont) {
            $entries[] = '/' . $fontAlias . ' ' . $fontObjectIds[$pageFont->key()] . ' 0 R';
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

        $optionalContentEntries = [];

        foreach ($optionalContentGroups as $alias => $optionalContentGroup) {
            $objectId = $optionalContentGroupObjectIds[$optionalContentGroup->key()] ?? null;

            if ($objectId === null) {
                continue;
            }

            $optionalContentEntries[] = '/' . $alias . ' ' . $objectId . ' 0 R';
        }

        if ($optionalContentEntries !== []) {
            $resourceEntries[] = '/Properties << ' . implode(' ', $optionalContentEntries) . ' >>';
        }

        return '<< ' . implode(' ', $resourceEntries) . ' >>';
    }

    /**
     * @param array<string, PageFont> $fontResources
     * @param array<string, int> $fontObjectIds
     * @return array<string, int>
     */
    private function pageFontObjectIdsByAlias(array $fontResources, array $fontObjectIds): array
    {
        $objectIdsByAlias = [];

        foreach ($fontResources as $fontAlias => $pageFont) {
            $fontObjectId = $fontObjectIds[$pageFont->key()] ?? null;

            if ($fontObjectId === null) {
                continue;
            }

            $objectIdsByAlias[$fontAlias] = $fontObjectId;
        }

        return $objectIdsByAlias;
    }

    /**
     * @param list<int> $pageObjectIds
     * @return array<int, int>
     */
    private function pageObjectIdsByPageNumber(array $pageObjectIds): array
    {
        $mapping = [];

        foreach ($pageObjectIds as $index => $pageObjectId) {
            $mapping[$index + 1] = $pageObjectId;
        }

        return $mapping;
    }

    /**
     * @param list<IndirectObject> $objects
     * @param list<IndirectObject> $additionalObjects
     */
    private function appendObjects(array &$objects, array $additionalObjects): void
    {
        foreach ($additionalObjects as $additionalObject) {
            $objects[] = $additionalObject;
        }
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
