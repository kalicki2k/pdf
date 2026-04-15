<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function in_array;

use Closure;

final class TaggedStructureRegistrar
{
    /** @var list<array{key: string, tag: string, pageIndex: int, markedContentId: int, parentKey: ?string}> */
    private array $taggedTextBlocks;
    /** @var list<array{key: string, pageIndex: int, markedContentId: int, altText: ?string}> */
    private array $taggedFigures;
    /** @var array<int, list<array{label: array{pageIndex: int, markedContentId: int}, body: array{pageIndex: int, markedContentId: int}}>> */
    private array $taggedLists;
    /** @var array<string, array{tag: string, childKeys: list<string>}> */
    private array $taggedStructureElements;
    /** @var list<string> */
    private array $taggedDocumentChildKeys;
    /** @var list<string> */
    private array $taggedStructureStack;
    private int $nextTaggedStructureElementId;

    /**
     * @param list<array{key: string, tag: string, pageIndex: int, markedContentId: int, parentKey: ?string}> $taggedTextBlocks
     * @param list<array{key: string, pageIndex: int, markedContentId: int, altText: ?string}> $taggedFigures
     * @param array<int, list<array{label: array{pageIndex: int, markedContentId: int}, body: array{pageIndex: int, markedContentId: int}}>> $taggedLists
     * @param array<string, array{tag: string, childKeys: list<string>}> $taggedStructureElements
     * @param list<string> $taggedDocumentChildKeys
     * @param list<string> $taggedStructureStack
     * @param Closure(int): array{pageIndex: int, markedContentId: int} $taggedContentReference
     */
    public function __construct(
        array &$taggedTextBlocks,
        array &$taggedFigures,
        array &$taggedLists,
        array &$taggedStructureElements,
        array &$taggedDocumentChildKeys,
        array &$taggedStructureStack,
        int &$nextTaggedStructureElementId,
        private readonly bool $requiresTaggedStructure,
        private readonly Closure $taggedContentReference,
    ) {
        $this->taggedTextBlocks = &$taggedTextBlocks;
        $this->taggedFigures = &$taggedFigures;
        $this->taggedLists = &$taggedLists;
        $this->taggedStructureElements = &$taggedStructureElements;
        $this->taggedDocumentChildKeys = &$taggedDocumentChildKeys;
        $this->taggedStructureStack = &$taggedStructureStack;
        $this->nextTaggedStructureElementId = &$nextTaggedStructureElementId;
    }

    public function registerTextBlock(string $tag, int $markedContentId, ?string $key = null, ?string $parentKey = null): string
    {
        $attachToStructure = $key === null && $parentKey === null;
        $key ??= 'text:' . count($this->taggedTextBlocks);
        $this->taggedTextBlocks[] = [
            'key' => $key,
            'tag' => $tag,
            'parentKey' => $parentKey,
            ...($this->taggedContentReference)($markedContentId),
        ];

        if ($attachToStructure) {
            $this->attachStructureChildKey($key);
        } elseif ($parentKey !== null) {
            $this->attachStructureChildKeyTo($parentKey, $key);
        }

        return $key;
    }

    /**
     * @param list<int> $markedContentIds
     */
    public function registerTextBlocks(string $tag, array $markedContentIds, ?string $key = null, ?string $parentKey = null): string
    {
        foreach ($markedContentIds as $markedContentId) {
            $key = $this->registerTextBlock($tag, $markedContentId, $key, $parentKey);
        }

        return $key ?? 'text:' . count($this->taggedTextBlocks);
    }

    public function registerInlineContainer(string $tag, ?string $key = null): string
    {
        $key ??= 'struct:' . $this->nextTaggedStructureElementId++;

        if (!isset($this->taggedStructureElements[$key])) {
            $this->taggedStructureElements[$key] = [
                'tag' => $tag,
                'childKeys' => [],
            ];
            $this->attachStructureChildKey($key);
        }

        return $key;
    }

    public function registerFigure(int $markedContentId, ?string $altText): void
    {
        $key = 'figure:graphics:' . count($this->taggedFigures);
        $this->taggedFigures[] = [
            'key' => $key,
            ...($this->taggedContentReference)($markedContentId),
            'altText' => $altText,
        ];
        $this->attachStructureChildKey($key);
    }

    public function registerListItem(int $listId, int $labelMarkedContentId, int $bodyMarkedContentId): void
    {
        if (!isset($this->taggedLists[$listId])) {
            $this->attachStructureChildKey('list:' . $listId);
        }

        $this->taggedLists[$listId][] = [
            'label' => ($this->taggedContentReference)($labelMarkedContentId),
            'body' => ($this->taggedContentReference)($bodyMarkedContentId),
        ];
    }

    public function attachStructureChildKey(string $key): void
    {
        if (!$this->requiresTaggedStructure) {
            return;
        }

        $containerKey = $this->taggedStructureStack[count($this->taggedStructureStack) - 1] ?? null;

        if ($containerKey === null) {
            $documentChildKeys = $this->taggedDocumentChildKeys;
            $documentChildKeys[] = $key;
            $this->taggedDocumentChildKeys = $documentChildKeys;

            return;
        }

        $this->attachStructureChildKeyTo($containerKey, $key);
    }

    public function attachStructureChildKeyTo(string $containerKey, string $childKey): void
    {
        if (!$this->requiresTaggedStructure) {
            return;
        }

        $existingChildKeys = $this->taggedStructureElements[$containerKey]['childKeys'];

        if (in_array($childKey, $existingChildKeys, true)) {
            return;
        }

        $this->taggedStructureElements[$containerKey]['childKeys'][] = $childKey;
    }
}
