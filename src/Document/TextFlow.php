<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\Page;

use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

use function max;
use function preg_split;
use function trim;

final readonly class TextFlow
{
    public function __construct(
        private Page $page,
        private ?float $cursorY = null,
    ) {
    }

    /**
     * @return array{x: float, y: float}
     */
    public function placement(TextOptions $options, StandardFontDefinition | EmbeddedFontDefinition $font): array
    {
        $contentArea = $this->page->contentArea();

        $x = $options->x
            ?? ($this->page->margin !== null ? $contentArea->left : 0.0);

        $topBoundary = $this->page->margin !== null
            ? $contentArea->top
            : $this->page->size->height();

        $resolvedY = $options->y
            ?? $this->cursorY
            ?? ($topBoundary - $this->topGlyphOffset($options, $font));

        $y = $options->y
            ?? ($resolvedY - $this->spacingBefore($options));

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

        foreach ($paragraphs as $paragraphIndex => $paragraph) {
            $trimmedParagraph = trim($paragraph);

            if ($trimmedParagraph === '') {
                $lines[] = '';

                continue;
            }

            $words = preg_split('/ +/u', $trimmedParagraph, -1, PREG_SPLIT_NO_EMPTY) ?: [$trimmedParagraph];
            $currentLine = array_shift($words);
            $lineMaxWidth = $this->lineAvailableWidth($x, $options, true);

            foreach ($words as $word) {
                $candidate = $currentLine . ' ' . $word;
                $candidateWidth = $font->measureTextWidth($candidate, $options->fontSize);

                if ($candidateWidth <= $lineMaxWidth) {
                    $currentLine = $candidate;

                    continue;
                }

                $lines[] = $currentLine;
                $currentLine = $word;
                $lineMaxWidth = $this->lineAvailableWidth($x, $options, false);
            }

            $lines[] = $currentLine;

            if ($paragraphIndex < count($paragraphs) - 1) {
                $lines[] = '';
            }
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

        foreach ($paragraphs as $paragraphIndex => $paragraphSegments) {
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
                $candidateWidth = $font->measureTextWidth($this->segmentsText($candidateLine), $options->fontSize);

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

            if ($paragraphIndex < count($paragraphs) - 1) {
                $lines[] = [];
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

        $rightBoundary = $this->page->margin !== null
            ? $this->page->contentArea()->right
            : $this->page->size->width();

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

                $paragraphs[array_key_last($paragraphs)][] = new TextSegment($part, $segment->link);
            }
        }

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
                $tokens[] = new TextSegment($part, $segment->link);
            }
        }

        while ($tokens !== [] && trim($tokens[0]->text) === '') {
            array_shift($tokens);
        }

        while ($tokens !== [] && trim($tokens[array_key_last($tokens)]->text) === '') {
            array_pop($tokens);
        }

        return $tokens;
    }

    /**
     * @param list<TextSegment> $segments
     */
    private function segmentsText(array $segments): string
    {
        return implode('', array_map(
            static fn (TextSegment $segment): string => $segment->text,
            $segments,
        ));
    }
}
