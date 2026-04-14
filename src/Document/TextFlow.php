<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function max;
use function preg_split;
use function trim;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Layout\PositionMode;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

final readonly class TextFlow
{
    public function __construct(
        private Page $page,
        private ?float $cursorY = null,
        private bool $cursorYIsTopBoundary = false,
    ) {
    }

    /**
     * @return array{x: float, y: float}
     */
    public function placement(TextOptions $options, StandardFontDefinition | EmbeddedFontDefinition $font): array
    {
        $pageHeight = $this->page->size->height();
        [
            'left' => $referenceLeft,
            'right' => $referenceRight,
            'top' => $referenceTop,
            'bottom' => $referenceBottom,
        ] = $this->positionReference($options);
        $defaultLeft = $this->page->margin !== null
            ? $this->page->contentArea()->left
            : 0.0;
        $rightBoundary = $this->resolvedRightBoundary($options);

        $x = $options->left
            ?? ($options->right !== null && $options->width !== null
                ? max($rightBoundary - $options->width, 0.0)
                : $defaultLeft);

        $topBoundary = $this->hasExplicitInsets($options)
            ? $referenceTop
            : ($this->page->margin !== null
                ? $this->page->contentArea()->top
                : $pageHeight);
        $topGlyphOffset = $this->topGlyphOffset($options, $font);

        if ($options->left !== null && $this->hasExplicitInsets($options)) {
            $x = $referenceLeft + $options->left;
        }

        $resolvedY = $options->top !== null
            ? $referenceTop - $options->top - $topGlyphOffset
            : ($options->bottom !== null
                ? $referenceBottom + $options->bottom
                : (($this->cursorY !== null
                    ? $this->cursorY - ($this->cursorYIsTopBoundary ? $topGlyphOffset : 0.0)
                    : null)
                ?? ($topBoundary - $topGlyphOffset)));

        $y = $options->top !== null || $options->bottom !== null
            ? $resolvedY
            : ($resolvedY - $this->spacingBefore($options));

        return [
            'x' => $x,
            'y' => $y,
        ];
    }

    /**
     * @return list<string>
     */
    public function wrapTextLines(
        string $text,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        float $x,
    ): array {
        $maxWidth = $this->lineAvailableWidth($x, $options, true);

        if ($maxWidth <= 0.0 || (!str_contains($text, ' ') && !str_contains($text, "\n") && !str_contains($text, "\r"))) {
            return [$text];
        }

        $paragraphs = preg_split("/\r\n|\r|\n/", $text) ?: [$text];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $trimmedParagraph = trim($paragraph);

            if ($trimmedParagraph === '') {
                $lines[] = '';

                continue;
            }

            $words = preg_split('/ +/u', $trimmedParagraph, -1, PREG_SPLIT_NO_EMPTY) ?: [$trimmedParagraph];
            $currentLine = $words[0];
            unset($words[0]);
            $wordWidths = [];
            $spaceWidth = $font->measureTextWidth(' ', $options->fontSize);
            $wordWidths[$currentLine] = $font->measureTextWidth($currentLine, $options->fontSize);
            $currentLineWidth = $wordWidths[$currentLine];
            $lineMaxWidth = $this->lineAvailableWidth($x, $options, true);

            foreach ($words as $word) {
                $wordWidth = $wordWidths[$word] ??= $font->measureTextWidth($word, $options->fontSize);
                $candidateWidth = $currentLineWidth + $spaceWidth + $wordWidth;

                if ($candidateWidth <= $lineMaxWidth) {
                    $currentLine .= ' ' . $word;
                    $currentLineWidth = $candidateWidth;

                    continue;
                }

                $lines[] = $currentLine;
                $currentLine = $word;
                $currentLineWidth = $wordWidth;
                $lineMaxWidth = $this->lineAvailableWidth($x, $options, false);
            }

            $lines[] = $currentLine;
        }

        return $lines;
    }

    /**
     * @param list<TextSegment> $segments
     * @return list<list<TextSegment>>
     */
    public function wrapSegmentLines(
        array $segments,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        float $x,
    ): array {
        $maxWidth = $this->lineAvailableWidth($x, $options, true);

        if ($segments === [] || $maxWidth <= 0.0) {
            return [$segments];
        }

        $paragraphs = $this->splitSegmentParagraphs($segments);
        $lines = [];

        foreach ($paragraphs as $paragraphSegments) {
            $tokens = $this->segmentTokens($paragraphSegments);

            if ($tokens === []) {
                $lines[] = [];

                continue;
            }

            $currentLine = [];
            $lineMaxWidth = $this->lineAvailableWidth($x, $options, true);

            foreach ($tokens as $token) {
                if ($currentLine === [] && trim($token->text) === '') {
                    continue;
                }

                $candidateLine = [...$currentLine, $token];
                $candidateWidth = $this->segmentsWidth($candidateLine, $options);

                if ($currentLine !== [] && $candidateWidth > $lineMaxWidth) {
                    $lines[] = $currentLine;
                    $currentLine = trim($token->text) === '' ? [] : [$token];
                    $lineMaxWidth = $this->lineAvailableWidth($x, $options, false);

                    continue;
                }

                $currentLine[] = $token;
            }

            if ($currentLine !== []) {
                $lines[] = $currentLine;
            }
        }

        return $lines;
    }

    public function nextCursorY(TextOptions $options, float $resolvedY, int $lineCount = 1): float
    {
        return $resolvedY - ($this->lineHeight($options) * max($lineCount, 1)) - $this->spacingAfter($options);
    }

    public function lineHeight(TextOptions $options): float
    {
        return $options->lineHeight ?? ($options->fontSize * 1.2);
    }

    public function availableTextWidthFrom(float $x, ?TextOptions $options = null): float
    {
        return $this->availableTextWidth($x, $options);
    }

    public function lineX(float $x, TextOptions $options, bool $isFirstLine): float
    {
        return $x + $this->lineIndent($options, $isFirstLine);
    }

    private function spacingAfter(TextOptions $options): float
    {
        return $options->spacingAfter ?? 0.0;
    }

    private function spacingBefore(TextOptions $options): float
    {
        return $options->spacingBefore ?? 0.0;
    }

    private function topGlyphOffset(
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): float {
        return $font->ascent($options->fontSize);
    }

    private function availableTextWidth(float $x, ?TextOptions $options = null): float
    {
        if ($options?->width !== null) {
            return max($options->width, 0.0);
        }

        $rightBoundary = $this->resolvedRightBoundary($options);

        $availableWidth = max($rightBoundary - $x, 0.0);

        if ($options?->maxWidth !== null) {
            return min($availableWidth, max($options->maxWidth, 0.0));
        }

        return $availableWidth;
    }

    private function lineAvailableWidth(float $x, TextOptions $options, bool $isFirstLine): float
    {
        $indent = $this->lineIndent($options, $isFirstLine);

        if ($options->width !== null) {
            return max($options->width - $indent, 0.0);
        }

        return $this->availableTextWidth($x + $indent, $options);
    }

    private function lineIndent(TextOptions $options, bool $isFirstLine): float
    {
        return $isFirstLine
            ? max($options->firstLineIndent, 0.0)
            : max($options->hangingIndent, 0.0);
    }

    /**
     * @param list<TextSegment> $segments
     * @return list<list<TextSegment>>
     */
    private function splitSegmentParagraphs(array $segments): array
    {
        $paragraphs = [[]];

        foreach ($segments as $segment) {
            $parts = preg_split("/(\r\n|\r|\n)/", $segment->text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$segment->text];

            foreach ($parts as $part) {
                if ($part === "\r\n" || $part === "\r" || $part === "\n") {
                    $paragraphs[] = [];

                    continue;
                }

                if ($part === '') {
                    continue;
                }

                $paragraphs[array_key_last($paragraphs)][] = new TextSegment($part, $segment->link, $segment->options);
            }
        }

        /** @var list<list<TextSegment>> $paragraphs */
        return $paragraphs;
    }

    /**
     * @param list<TextSegment> $segments
     * @return list<TextSegment>
     */
    private function segmentTokens(array $segments): array
    {
        $tokens = [];

        foreach ($segments as $segment) {
            $parts = preg_split('/(\s+)/u', $segment->text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$segment->text];

            foreach ($parts as $part) {
                $tokens[] = new TextSegment($part, $segment->link, $segment->options);
            }
        }

        while ($tokens !== [] && trim($tokens[0]->text) === '') {
            array_shift($tokens);
        }

        while ($tokens !== [] && trim(array_last($tokens)->text) === '') {
            array_pop($tokens);
        }

        return $tokens;
    }

    /**
     * @param list<TextSegment> $segments
     */
    private function segmentsWidth(array $segments, TextOptions $baseOptions): float
    {
        $width = 0.0;

        foreach ($segments as $segment) {
            $options = $this->segmentTextOptions($baseOptions, $segment);
            $font = $options->embeddedFont !== null
                ? EmbeddedFontDefinition::fromSource($options->embeddedFont)
                : StandardFontDefinition::from($options->fontName);
            $width += $font->measureTextWidth($segment->text, $options->fontSize);
        }

        return $width;
    }

    private function segmentTextOptions(TextOptions $baseOptions, TextSegment $segment): TextOptions
    {
        if ($segment->options === null) {
            return $baseOptions;
        }

        return TextOptions::make(
            left: $baseOptions->left,
            right: $baseOptions->right,
            bottom: $baseOptions->bottom,
            top: $baseOptions->top,
            positionMode: $baseOptions->positionMode,
            width: $baseOptions->width,
            maxWidth: $baseOptions->maxWidth,
            fontSize: $this->segmentFontSize($baseOptions, $segment->options),
            lineHeight: $baseOptions->lineHeight,
            spacingBefore: $baseOptions->spacingBefore,
            spacingAfter: $baseOptions->spacingAfter,
            fontName: $this->segmentFontName($baseOptions, $segment->options),
            embeddedFont: $segment->options->embeddedFont ?? $baseOptions->embeddedFont,
            fontEncoding: $segment->options->fontEncoding ?? $baseOptions->fontEncoding,
            color: $segment->options->color ?? $baseOptions->color,
            kerning: $this->segmentKerning($baseOptions, $segment->options),
            baseDirection: $this->segmentBaseDirection($baseOptions, $segment->options),
            align: $baseOptions->align,
            firstLineIndent: $baseOptions->firstLineIndent,
            hangingIndent: $baseOptions->hangingIndent,
            link: $segment->link ?? $baseOptions->link,
            tag: $baseOptions->tag,
            semantic: $baseOptions->semantic,
        );
    }

    private function resolvedRightBoundary(?TextOptions $options = null): float
    {
        if ($options !== null && $this->hasExplicitInsets($options)) {
            ['right' => $rightBoundary] = $this->positionReference($options);

            return $rightBoundary - ($options->right ?? 0.0);
        }

        $rightBoundary = $this->page->margin !== null
            ? $this->page->contentArea()->right
            : $this->page->size->width();

        return $rightBoundary - ($options?->right ?? 0.0);
    }

    /**
     * @return array{left: float, right: float, top: float, bottom: float}
     */
    private function positionReference(?TextOptions $options = null): array
    {
        if ($options?->positionMode === PositionMode::RELATIVE) {
            $contentArea = $this->page->contentArea();

            return [
                'left' => $contentArea->left,
                'right' => $contentArea->right,
                'top' => $contentArea->top,
                'bottom' => $contentArea->bottom,
            ];
        }

        return [
            'left' => 0.0,
            'right' => $this->page->size->width(),
            'top' => $this->page->size->height(),
            'bottom' => 0.0,
        ];
    }

    private function hasExplicitInsets(TextOptions $options): bool
    {
        return $options->left !== null
            || $options->right !== null
            || $options->top !== null
            || $options->bottom !== null;
    }

    private function segmentFontSize(TextOptions $baseOptions, TextOptions $segmentOptions): float
    {
        if ($segmentOptions->fontSize === 18.0 && $baseOptions->fontSize !== 18.0) {
            return $baseOptions->fontSize;
        }

        return $segmentOptions->fontSize;
    }

    private function segmentFontName(TextOptions $baseOptions, TextOptions $segmentOptions): string
    {
        if ($segmentOptions->fontName === StandardFont::HELVETICA->value && $baseOptions->fontName !== StandardFont::HELVETICA->value) {
            return $baseOptions->fontName;
        }

        return $segmentOptions->fontName;
    }

    private function segmentKerning(TextOptions $baseOptions, TextOptions $segmentOptions): bool
    {
        if ($segmentOptions->kerning && !$baseOptions->kerning) {
            return $baseOptions->kerning;
        }

        return $segmentOptions->kerning;
    }

    private function segmentBaseDirection(TextOptions $baseOptions, TextOptions $segmentOptions): TextDirection
    {
        if ($segmentOptions->baseDirection === TextDirection::LTR && $baseOptions->baseDirection !== TextDirection::LTR) {
            return $baseOptions->baseDirection;
        }

        return $segmentOptions->baseDirection;
    }
}
