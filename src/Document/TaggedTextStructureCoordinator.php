<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Closure;

final readonly class TaggedTextStructureCoordinator
{
    /**
     * @param Closure(string, ?string): string $registerInlineContainer
     * @param Closure(string, list<int>, ?string): string $registerTaggedTextBlocks
     */
    public function __construct(
        private bool $requiresTaggedLinkAnnotations,
        private Closure $registerInlineContainer,
        private Closure $registerTaggedTextBlocks,
    ) {
    }

    public function resolveInlineContainerKey(
        ?string $markedContentTag,
        bool $containsLinks,
        ?string $existingKey = null,
    ): ?string {
        if ($markedContentTag === null || !$containsLinks || !$this->requiresTaggedLinkAnnotations) {
            return null;
        }

        return ($this->registerInlineContainer)($markedContentTag, $existingKey);
    }

    /**
     * @param list<int> $textMarkedContentIds
     */
    public function resolveTaggedTextKey(
        ?string $inlineContainerKey,
        ?string $markedContentTag,
        string $contents,
        array $textMarkedContentIds,
        ?string $taggedTextKey = null,
    ): ?string {
        if ($inlineContainerKey !== null) {
            return $inlineContainerKey;
        }

        if ($markedContentTag === null || $contents === '' || $textMarkedContentIds === []) {
            return $taggedTextKey;
        }

        return ($this->registerTaggedTextBlocks)($markedContentTag, $textMarkedContentIds, $taggedTextKey);
    }
}
