<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

use InvalidArgumentException;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\PageFonts;
use Kalle\Pdf\Document\PageGraphics;
use Kalle\Pdf\Document\PageLinks;
use Kalle\Pdf\Document\PageMarkedContentIds;
use Kalle\Pdf\Element\Text as TextElement;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Renders a single text run onto a page.
 */
final class PageTextElementRenderer
{
    public function __construct(
        private readonly Page $page,
        private readonly PageFonts $pageFonts,
        private readonly PageLinks $pageLinks,
        private readonly PageGraphics $pageGraphics,
        private readonly PageMarkedContentIds $pageMarkedContentIds,
    ) {
    }

    public function render(
        string $text,
        Position $position,
        string $fontName = 'Helvetica',
        int $size = 12,
        TextOptions $options = new TextOptions(),
    ): Page {
        $structureTag = $this->resolveMarkedContentStructureTag($options);
        $artifactTag = $structureTag === null && $this->page->getDocument()->isRenderingArtifactContext()
            ? 'Artifact'
            : null;
        $contentTag = $structureTag !== null
            ? $structureTag->value
            : $artifactTag;

        if ($structureTag !== null) {
            $this->page->getDocument()->ensureStructureEnabled();
        }

        $font = $this->resolveFont($fontName);
        $markedContentId = $structureTag !== null ? $this->nextMarkedContentId() : null;
        $encodedText = $this->encodeText($font, $fontName, $text);
        $resourceFontName = $this->registerFontResource($font);
        $textWidth = $font->measureTextWidth($text, $size);
        [$leadingDecorationInset, $trailingDecorationInset] = $this->resolveDecorationInsets($font, $text, $size);
        $colorOperator = $options->color?->renderNonStrokingOperator();
        $graphicsStateName = $this->resolveGraphicsStateName($options->opacity);

        $this->updateUnicodeFontWidths($font);

        $this->page->contents->addElement(new TextElement(
            $markedContentId,
            $encodedText,
            $position->x,
            $position->y,
            $resourceFontName,
            $size,
            $textWidth,
            $colorOperator,
            $graphicsStateName,
            $options->underline,
            $options->strikethrough,
            $contentTag,
            $leadingDecorationInset,
            $trailingDecorationInset,
        ));

        $textStructElem = null;

        if ($structureTag !== null && $markedContentId !== null) {
            $textStructElem = $this->attachTextToStructure($options, $structureTag, $markedContentId, $text);
        }

        if ($options->link !== null && $textWidth > 0.0) {
            $this->addLinkTarget(
                new Rect(
                    $position->x,
                    $position->y - ($size * 0.2),
                    $textWidth,
                    $size,
                ),
                $options->link,
                $textStructElem,
                $this->resolveLinkAlternativeDescription($text),
            );
        }

        return $this->page;
    }

    private function resolveFont(string $baseFont): FontDefinition
    {
        return $this->pageFonts->resolveFont($baseFont);
    }

    private function registerFontResource(FontDefinition $font): string
    {
        return $this->pageFonts->registerFontResource($font);
    }

    private function updateUnicodeFontWidths(FontDefinition $font): void
    {
        $this->pageFonts->updateUnicodeFontWidths($font);
    }

    private function resolveMarkedContentStructureTag(TextOptions $options): ?StructureTag
    {
        return $this->pageLinks->resolveMarkedContentStructureTag($options);
    }

    private function attachTextToStructure(TextOptions $options, StructureTag $tag, int $markedContentId, string $text): StructElem
    {
        return $this->pageLinks->attachTextToStructure($options, $tag, $markedContentId, $text);
    }

    private function resolveLinkAlternativeDescription(string $text): ?string
    {
        return $this->pageLinks->resolveLinkAlternativeDescription($text);
    }

    private function addLinkTarget(
        Rect $box,
        LinkTarget $target,
        ?StructElem $linkStructElem = null,
        ?string $alternativeDescription = null,
    ): void {
        $this->pageLinks->addLinkTarget($box, $target, $linkStructElem, $alternativeDescription);
    }

    private function resolveGraphicsStateName(?Opacity $opacity): ?string
    {
        return $this->pageGraphics->resolveGraphicsStateName($opacity);
    }

    private function nextMarkedContentId(): int
    {
        return $this->pageMarkedContentIds->next();
    }

    private function encodeText(FontDefinition $font, string $baseFont, string $text): string
    {
        if (!$font->supportsText($text)) {
            throw new InvalidArgumentException("Font '$baseFont' does not support the provided text.");
        }

        return $font->encodeText($text);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveDecorationInsets(FontDefinition $font, string $text, int $size): array
    {
        if ($text === '') {
            return [0.0, 0.0];
        }

        $leadingSpaces = strspn($text, ' ');
        $trailingSpaces = strlen($text) - strlen(rtrim($text, ' '));

        $leadingInset = $leadingSpaces > 0
            ? $font->measureTextWidth(str_repeat(' ', $leadingSpaces), $size)
            : 0.0;

        $trailingInset = $trailingSpaces > 0
            ? $font->measureTextWidth(str_repeat(' ', $trailingSpaces), $size)
            : 0.0;

        return [$leadingInset, $trailingInset];
    }
}
