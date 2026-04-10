<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Text;

use Kalle\Pdf\Internal\Font\FontDefinition;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\Position;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructureTag;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

/**
 * @internal Renders already prepared text lines onto a page.
 */
final class PageTextLineRenderer
{
    public function __construct(
        private readonly PageFonts $pageFonts,
        private readonly TextLayoutEngine $textLayoutEngine,
    ) {
    }

    /**
     * @param array{segments: array<int, TextSegment>, justify: bool} $line
     */
    public function render(
        Page $page,
        array $line,
        float $x,
        float $y,
        float $maxWidth,
        string $baseFont,
        int $size,
        ?StructureTag $tag,
        ?StructElem $parentStructElem,
        HorizontalAlign $align,
    ): void {
        if ($line['segments'] === []) {
            return;
        }

        $cursorX = $x + $this->calculateAlignedOffset($line['segments'], $baseFont, $size, $maxWidth, $align);

        if ($align === HorizontalAlign::JUSTIFY && $line['justify']) {
            $this->renderJustifiedLine($page, $line['segments'], $cursorX, $y, $baseFont, $size, $tag, $maxWidth, $parentStructElem);

            return;
        }

        foreach ($line['segments'] as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);

            $page->addText(
                $segment->text,
                new Position($cursorX, $y),
                $segmentFontName,
                $size,
                $this->createSegmentTextOptions($segment, $tag, $parentStructElem),
            );
            $cursorX += $segmentFont->measureTextWidth($segment->text, $size);
        }
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function calculateAlignedOffset(
        array $line,
        string $baseFont,
        int $size,
        float $maxWidth,
        HorizontalAlign $align,
    ): float {
        if ($align === HorizontalAlign::LEFT || $align === HorizontalAlign::JUSTIFY) {
            return 0.0;
        }

        $line = $this->textLayoutEngine->trimTrailingWhitespaceFromLine($line);

        $lineWidth = 0.0;

        foreach ($line as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);
            $lineWidth += $segmentFont->measureTextWidth($segment->text, $size);
        }

        $remainingWidth = max(0.0, $maxWidth - $lineWidth);

        if ($align === HorizontalAlign::CENTER) {
            return $remainingWidth / 2;
        }

        return $remainingWidth;
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function renderJustifiedLine(
        Page $page,
        array $line,
        float $x,
        float $y,
        string $baseFont,
        int $size,
        ?StructureTag $tag,
        float $maxWidth,
        ?StructElem $parentStructElem,
    ): void {
        $pieces = $this->splitSegmentsIntoWordPieces($line);
        $extraWordSpacing = $this->calculateJustifiedWordSpacing($line, $baseFont, $size, $maxWidth);
        $cursorX = $x;
        $isFirstWord = true;

        foreach ($pieces as $piece) {
            $segment = $piece['segment'];
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);

            if (!$isFirstWord) {
                $spaceWidth = $segmentFont->measureTextWidth(str_repeat(' ', $piece['leadingSpaces']), $size);
                $cursorX += $spaceWidth + ($extraWordSpacing * $piece['leadingSpaces']);
            }

            $page->addText(
                $segment->text,
                new Position($cursorX, $y),
                $segmentFontName,
                $size,
                $this->createSegmentTextOptions($segment, $tag, $parentStructElem),
            );

            $cursorX += $segmentFont->measureTextWidth($segment->text, $size);
            $isFirstWord = false;
        }
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function calculateJustifiedWordSpacing(
        array $line,
        string $baseFont,
        int $size,
        float $maxWidth,
    ): float {
        $lineWidth = 0.0;
        $spaceCount = 0;
        $pieces = $this->splitSegmentsIntoWordPieces($line);

        foreach ($line as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);
            $lineWidth += $segmentFont->measureTextWidth($segment->text, $size);
        }

        foreach ($pieces as $index => $piece) {
            if ($index === 0) {
                continue;
            }

            $spaceCount += $piece['leadingSpaces'];
        }

        if ($spaceCount <= 0) {
            return 0.0;
        }

        return max(0.0, $maxWidth - $lineWidth) / $spaceCount;
    }

    /**
     * @param array<int, TextSegment> $segments
     * @return list<array{segment: TextSegment, leadingSpaces: int}>
     */
    private function splitSegmentsIntoWordPieces(array $segments): array
    {
        $pieces = [];

        foreach ($segments as $segment) {
            $leadingSpaces = 0;

            foreach (preg_split('/( +)/', $segment->text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if (trim($part) === '') {
                    $leadingSpaces += strlen($part);
                    continue;
                }

                $pieces[] = [
                    'segment' => new TextSegment(
                        $part,
                        $segment->color,
                        $segment->opacity,
                        $segment->link,
                        $segment->bold,
                        $segment->italic,
                        $segment->underline,
                        $segment->strikethrough,
                    ),
                    'leadingSpaces' => $leadingSpaces,
                ];

                $leadingSpaces = 0;
            }
        }

        return $pieces;
    }

    private function resolveFont(string $baseFont): FontDefinition
    {
        return $this->pageFonts->resolveFont($baseFont);
    }

    private function resolveStyledBaseFont(string $baseFont, TextSegment $segment): string
    {
        return $this->pageFonts->resolveStyledBaseFont($baseFont, $segment);
    }

    private function createSegmentTextOptions(TextSegment $segment, ?StructureTag $tag, ?StructElem $parentStructElem): TextOptions
    {
        return new TextOptions(
            structureTag: $tag,
            parentStructElem: $parentStructElem,
            color: $segment->color,
            opacity: $segment->opacity,
            underline: $segment->underline,
            strikethrough: $segment->strikethrough,
            link: $segment->link,
        );
    }
}
