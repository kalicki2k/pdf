<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class CollectedTaggedStructure
{
    /**
     * @param list<array{key: string, pageIndex: int, markedContentId: int, altText: ?string}> $figureEntries
     * @param list<array{key: string, tag: string, pageIndex: int, markedContentId: int}> $textEntries
     * @param list<array{
     *   key: string,
     *   listId: int,
     *   itemEntries: list<array{
     *     key: string,
     *     itemIndex: int,
     *     labelKey: string,
     *     bodyKey: string,
     *     labelReference: TaggedListContentReference,
     *     bodyReference: TaggedListContentReference
     *   }>
     * }> $listEntries
     * @param array<int, array<int, string>> $pageMarkedContentKeys
     */
    public function __construct(
        public array $figureEntries,
        public array $textEntries,
        public array $listEntries,
        public array $pageMarkedContentKeys,
    ) {
    }

    public function hasStructuredContent(): bool
    {
        return $this->figureEntries !== []
            || $this->textEntries !== []
            || $this->listEntries !== []
            || $this->pageMarkedContentKeys !== [];
    }

    public function hasMarkedContentOnPage(int $pageIndex): bool
    {
        return ($this->pageMarkedContentKeys[$pageIndex] ?? []) !== [];
    }
}
