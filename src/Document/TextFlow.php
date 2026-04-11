<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\Page;

use Kalle\Pdf\Text\TextOptions;

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
    public function placement(TextOptions $options, StandardFontDefinition|EmbeddedFontDefinition $font): array
    {
        $contentArea = $this->page->contentArea();

        $x = $options->x
            ?? ($this->page->margin !== null ? $contentArea->left : 0.0);

        $topBoundary = $this->page->margin !== null
            ? $contentArea->top
            : $this->page->size->height();

        $y = $options->y
            ?? $this->cursorY
            ?? ($topBoundary - $this->topGlyphOffset($options, $font));

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
        StandardFontDefinition|EmbeddedFontDefinition $font,
        float $x,
    ): array
    {
        $maxWidth = $this->availableTextWidth($x);

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

            foreach ($words as $word) {
                $candidate = $currentLine . ' ' . $word;
                $candidateWidth = $font->measureTextWidth($candidate, $options->fontSize);

                if ($candidateWidth <= $maxWidth) {
                    $currentLine = $candidate;

                    continue;
                }

                $lines[] = $currentLine;
                $currentLine = $word;
            }

            $lines[] = $currentLine;

            if ($paragraphIndex < count($paragraphs) - 1) {
                $lines[] = '';
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

    private function spacingAfter(TextOptions $options): float
    {
        return $options->spacingAfter ?? 0.0;
    }

    private function topGlyphOffset(
        TextOptions $options,
        StandardFontDefinition|EmbeddedFontDefinition $font,
    ): float {
        return $font->ascent($options->fontSize);
    }

    private function availableTextWidth(float $x): float
    {
        $rightBoundary = $this->page->margin !== null
            ? $this->page->contentArea()->right
            : $this->page->size->width();

        return max($rightBoundary - $x, 0.0);
    }
}
