<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function implode;

use Closure;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Text\TextLink;
use LogicException;

final readonly class TaggedTextLinkCoordinator
{
    /**
     * @param Closure(LinkTarget|TextLink): ?string $linkGroupKeyResolver
     * @param Closure(): int $nextMarkedContentId
     * @param Closure(): ?int $nextTaggedMarkedContentId
     * @param Closure(string, int, string): string $wrapMarkedContent
     * @param Closure(?string, ?int, ?string): void $attachInlineLinkChild
     * @param Closure(int, string): void $registerInlineSpan
     */
    public function __construct(
        private Closure $linkGroupKeyResolver,
        private bool $requiresTaggedPdfProfile,
        private bool $requiresTaggedLinkAnnotations,
        private Closure $nextMarkedContentId,
        private Closure $nextTaggedMarkedContentId,
        private Closure $wrapMarkedContent,
        private Closure $attachInlineLinkChild,
        private Closure $registerInlineSpan,
    ) {
    }

    public function taggedTextLinkGroupKey(LinkTarget | TextLink | null $link, int $pageIndex, int $fallbackIndex): ?string
    {
        if ($link === null) {
            return null;
        }

        return ($this->linkGroupKeyResolver)($link) ?? ('page-' . $pageIndex . '-text-link-' . $fallbackIndex);
    }

    /**
     * @return array{contents: string, annotation: LinkAnnotation}
     */
    public function buildLinkedTextRunContent(
        LinkTarget $link,
        string $contents,
        string $accessibleLabel,
        string $textBlockContent,
        float $x,
        float $y,
        float $width,
        float $lineHeight,
        float $ascent,
        ?string $taggedGroupKey = null,
        ?int $pageIndex = null,
        ?string $inlineContainerKey = null,
    ): array {
        $markedContentId = $this->requiresTaggedLinkAnnotations
            ? ($this->nextMarkedContentId)()
            : null;

        if ($markedContentId !== null) {
            $textBlockContent = implode("\n", [
                '/Link << /MCID ' . $markedContentId . ' >> BDC',
                $textBlockContent,
                'EMC',
            ]);

            ($this->attachInlineLinkChild)($inlineContainerKey, $pageIndex, $taggedGroupKey);
        }

        return [
            'contents' => $textBlockContent,
            'annotation' => new LinkAnnotation(
                target: $link,
                x: $x,
                y: $y - max($lineHeight - $ascent, 0.0),
                width: $width,
                height: $lineHeight,
                contents: $contents,
                accessibleLabel: $accessibleLabel,
                markedContentId: $markedContentId,
                taggedGroupKey: $taggedGroupKey,
            ),
        ];
    }

    public function wrapTaggedInlineSpanContent(
        string $textBlockContent,
        string $markedContentTag,
        string $inlineContainerKey,
    ): string {
        $entryMarkedContentId = ($this->nextTaggedMarkedContentId)();

        if ($entryMarkedContentId === null) {
            throw new LogicException('Tagged text segments require marked-content ids.');
        }

        $textBlockContent = ($this->wrapMarkedContent)(
            $markedContentTag,
            $entryMarkedContentId,
            $textBlockContent,
        );
        ($this->registerInlineSpan)($entryMarkedContentId, $inlineContainerKey);

        return $textBlockContent;
    }

    /**
     * @param list<int> $textMarkedContentIds
     * @return array{contents: string, textMarkedContentIds: list<int>}
     */
    public function finalizeTaggedTextContents(
        string $contentsString,
        bool $artifact,
        ?string $markedContentTag,
        ?int $markedContentId,
        bool $containsTaggedLinkText,
        array $textMarkedContentIds,
        Closure $wrapArtifactGraphics,
    ): array {
        if ($contentsString !== '' && $artifact && $this->requiresTaggedPdfProfile) {
            $contentsString = $wrapArtifactGraphics($contentsString);
            /** @var string $contentsString */
        } elseif (
            $contentsString !== ''
            && $markedContentTag !== null
            && $markedContentId !== null
            && !$containsTaggedLinkText
        ) {
            $contentsString = ($this->wrapMarkedContent)($markedContentTag, $markedContentId, $contentsString);
            $textMarkedContentIds[] = $markedContentId;
        }

        return [
            'contents' => $contentsString,
            'textMarkedContentIds' => $textMarkedContentIds,
        ];
    }
}
