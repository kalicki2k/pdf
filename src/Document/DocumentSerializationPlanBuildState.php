<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\TaggedPdf\CollectedTaggedStructure;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureObjectIds;
use Kalle\Pdf\Page\PageFont;

final readonly class DocumentSerializationPlanBuildState
{
    /**
     * @param list<int> $pageObjectIds
     * @param list<int> $contentObjectIds
     * @param array<string, int> $fontObjectIds
     * @param array<string, int> $fontDescriptorObjectIds
     * @param array<string, int> $fontFileObjectIds
     * @param array<string, int> $cidFontObjectIds
     * @param array<string, int> $toUnicodeObjectIds
     * @param array<string, int> $cidToGidMapObjectIds
     * @param array<string, int> $cidSetObjectIds
     * @param array<string, int> $imageObjectIds
     * @param array<int, list<int>> $pageAnnotationObjectIds
     * @param array<int, list<?int>> $pageAnnotationAppearanceObjectIds
     * @param array<int, array<int, list<int>>> $pageAnnotationRelatedObjectIds
     * @param list<int> $attachmentObjectIds
     * @param list<int> $embeddedFileObjectIds
     * @param list<int> $acroFormFieldObjectIds
     * @param array<int, list<int>> $acroFormFieldRelatedObjectIds
     * @param array<int, list<int>> $pageFormWidgetObjectIds
     * @param array<int, int> $pageStructParentIds
     * @param array{
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
     * } $taggedLinkStructure
     * @param array{
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
     * } $taggedPageAnnotationStructure
     * @param array{
     *   entries: list<array{
     *     key: string,
     *     annotationObjectId: int,
     *     pageIndex: int,
     *     altText: string
     *   }>,
     *   structParentIds: array<int, int>,
     *   parentTreeEntries: array<int, list<string>>
     * } $taggedFormStructure
     * @param array<string, string> $namedDestinations
     * @param list<int> $outlineItemObjectIds
     * @param array<string, int> $taggedFormStructElemObjectIds
     */
    public function __construct(
        public array $pageObjectIds,
        public array $contentObjectIds,
        public array $fontObjectIds,
        public array $fontDescriptorObjectIds,
        public array $fontFileObjectIds,
        public array $cidFontObjectIds,
        public array $toUnicodeObjectIds,
        public array $cidToGidMapObjectIds,
        public array $cidSetObjectIds,
        public array $imageObjectIds,
        public array $pageAnnotationObjectIds,
        public array $pageAnnotationAppearanceObjectIds,
        public array $pageAnnotationRelatedObjectIds,
        public array $attachmentObjectIds,
        public array $embeddedFileObjectIds,
        public ?int $acroFormObjectId,
        public array $acroFormFieldObjectIds,
        public array $acroFormFieldRelatedObjectIds,
        public array $pageFormWidgetObjectIds,
        public CollectedTaggedStructure $taggedStructure,
        public array $pageStructParentIds,
        public array $taggedLinkStructure,
        public array $taggedPageAnnotationStructure,
        public array $taggedFormStructure,
        public array $namedDestinations,
        public ?int $outlineRootObjectId,
        public array $outlineItemObjectIds,
        public ?int $structTreeRootObjectId,
        public ?int $documentStructElemObjectId,
        public ?int $parentTreeObjectId,
        public TaggedStructureObjectIds $taggedStructureObjectIds,
        public array $taggedFormStructElemObjectIds,
        public ?int $metadataObjectId,
        public ?int $iccProfileObjectId,
        public ?int $infoObjectId,
        public ?int $encryptObjectId,
        /** @var array<string, int> */
        public array $optionalContentGroupObjectIds = [],
        public ?PageFont $acroFormDefaultFont = null,
        public ?string $acroFormDefaultFontKey = null,
    ) {
    }
}
