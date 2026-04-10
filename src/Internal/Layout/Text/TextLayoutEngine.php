<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Text;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Font\FontDefinition;
use Kalle\Pdf\Internal\Layout\Value\TextOverflow;
use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\Text\TextSegment;

/**
 * Keeps text run normalization, line breaking and overflow rules together.
 */
final readonly class TextLayoutEngine
{
    public function __construct(private TextLayoutFontResolver $fontResolver)
    {
    }

    public static function forPageFonts(PageFonts $pageFonts): self
    {
        return new self(TextLayoutFontResolver::forPageFonts($pageFonts));
    }

    /**
     * @param string|list<TextSegment> $text
     * @return list<array{segments: array<int, TextSegment>, justify: bool}>
     */
    public function layoutParagraphLines(
        string | array $text,
        string $baseFont,
        int $size,
        float $maxWidth,
        ?Color $color = null,
        ?Opacity $opacity = null,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): array {
        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($maxLines !== null && $maxLines <= 0) {
            throw new InvalidArgumentException('Max lines must be greater than zero.');
        }

        $runs = $this->normalizeTextRuns($text, $color, $opacity);

        return $this->applyOverflowToLines(
            $this->wrapRunsIntoLines($runs, $baseFont, $size, $maxWidth),
            $baseFont,
            $size,
            $maxWidth,
            $maxLines,
            $overflow,
        );
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function countParagraphLines(
        string | array $text,
        string $baseFont,
        int $size,
        float $maxWidth,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): int {
        return count($this->layoutParagraphLines($text, $baseFont, $size, $maxWidth, null, null, $maxLines, $overflow));
    }

    /**
     * Alignment should ignore trailing spaces because they are not visible text.
     *
     * @param array<int, TextSegment> $line
     * @return array<int, TextSegment>
     */
    public function trimTrailingWhitespaceFromLine(array $line): array
    {
        while ($line !== []) {
            $lastIndex = array_key_last($line);
            $trimmed = rtrim($line[$lastIndex]->text, ' ');

            if ($trimmed === $line[$lastIndex]->text) {
                break;
            }

            if ($trimmed === '') {
                unset($line[$lastIndex]);
                $line = array_values($line);
                continue;
            }

            $this->replaceLastLineSegmentText($line, $trimmed);
            break;
        }

        return array_values($line);
    }

    /**
     * @return list<string>
     */
    private function breakWordToFit(string $word, FontDefinition $font, int $size, float $maxWidth): array
    {
        if ($font->measureTextWidth($word, $size) <= $maxWidth) {
            return [$word];
        }

        $chunks = [];
        $currentChunk = '';

        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $candidate = $currentChunk . $character;

            if ($currentChunk !== '' && $font->measureTextWidth($candidate, $size) > $maxWidth) {
                $chunks[] = $currentChunk;
                $currentChunk = $character;
                continue;
            }

            $currentChunk = $candidate;
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * @param string|array<mixed> $text
     * @return list<TextSegment>
     */
    private function normalizeTextRuns(string | array $text, ?Color $color, ?Opacity $opacity): array
    {
        if (is_string($text)) {
            return [new TextSegment($text, $color, $opacity)];
        }

        $runs = [];

        foreach ($text as $segment) {
            if (!$segment instanceof TextSegment) {
                throw new InvalidArgumentException('Paragraph text arrays must contain only TextSegment instances.');
            }

            $runs[] = $segment->withDefaults($color, $opacity);
        }

        return $runs === [] ? [new TextSegment('', $color, $opacity)] : $runs;
    }

    /**
     * @param list<TextSegment> $runs
     * @return list<array{segments: array<int, TextSegment>, justify: bool}>
     */
    private function wrapRunsIntoLines(array $runs, string $baseFont, int $size, float $maxWidth): array
    {
        /** @var list<array{segments: array<int, TextSegment>, justify: bool}> $lines */
        $lines = [];
        /** @var list<TextSegment> $currentLine */
        $currentLine = [];
        $currentLineWidth = 0.0;
        $pendingSpace = false;

        foreach ($runs as $run) {
            foreach ($this->tokenizeRun($run) as $token) {
                if ($token['type'] === 'newline') {
                    $lines[] = ['segments' => $currentLine, 'justify' => false];
                    $currentLine = [];
                    $currentLineWidth = 0.0;
                    $pendingSpace = false;
                    continue;
                }

                if ($token['type'] === 'space') {
                    $pendingSpace = $currentLine !== [];
                    continue;
                }

                /** @var TextSegment $wordRun */
                $wordRun = $token['run'];
                $wordFont = $this->resolveFont($this->resolveStyledBaseFont($baseFont, $wordRun));
                $text = ($pendingSpace && $currentLine !== [] ? ' ' : '') . $wordRun->text;
                $textWidth = $wordFont->measureTextWidth($text, $size);

                if ($currentLineWidth + $textWidth <= $maxWidth) {
                    $this->appendRun($currentLine, new TextSegment(
                        $text,
                        $wordRun->color,
                        $wordRun->opacity,
                        $wordRun->link,
                        $wordRun->bold,
                        $wordRun->italic,
                        $wordRun->underline,
                        $wordRun->strikethrough,
                    ));
                    $currentLineWidth += $textWidth;
                    $pendingSpace = false;
                    continue;
                }

                if ($currentLine !== []) {
                    $lines[] = ['segments' => $currentLine, 'justify' => true];
                    $currentLine = [];
                    $currentLineWidth = 0.0;
                    $pendingSpace = false;
                    $text = $wordRun->text;
                }

                $chunks = $this->breakWordToFit($text, $wordFont, $size, $maxWidth);

                foreach ($chunks as $index => $chunk) {
                    if ($index === count($chunks) - 1) {
                        $currentLine = [new TextSegment(
                            $chunk,
                            $wordRun->color,
                            $wordRun->opacity,
                            $wordRun->link,
                            $wordRun->bold,
                            $wordRun->italic,
                            $wordRun->underline,
                            $wordRun->strikethrough,
                        )];
                        $currentLineWidth = $wordFont->measureTextWidth($chunk, $size);
                        continue;
                    }

                    $lines[] = ['segments' => [new TextSegment(
                        $chunk,
                        $wordRun->color,
                        $wordRun->opacity,
                        $wordRun->link,
                        $wordRun->bold,
                        $wordRun->italic,
                        $wordRun->underline,
                        $wordRun->strikethrough,
                    )], 'justify' => true];
                }
            }
        }

        if ($currentLine !== []) {
            $lines[] = ['segments' => $currentLine, 'justify' => false];
        }

        return $lines === [] ? [['segments' => [], 'justify' => false]] : $lines;
    }

    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $lines
     * @return list<array{segments: array<int, TextSegment>, justify: bool}>
     */
    private function applyOverflowToLines(
        array $lines,
        string $baseFont,
        int $size,
        float $maxWidth,
        ?int $maxLines,
        TextOverflow $overflow,
    ): array {
        if ($maxLines === null || count($lines) <= $maxLines) {
            return $lines;
        }

        $visibleLines = array_slice($lines, 0, $maxLines);

        if ($overflow === TextOverflow::CLIP || $visibleLines === []) {
            return array_map(
                static fn (array $line): array => ['segments' => $line['segments'], 'justify' => false],
                $visibleLines,
            );
        }

        $lastIndex = array_key_last($visibleLines);
        $visibleLines[$lastIndex] = [
            'segments' => $this->appendEllipsisToLine($visibleLines[$lastIndex]['segments'], $baseFont, $size, $maxWidth),
            'justify' => false,
        ];

        return $visibleLines;
    }

    /**
     * @param array<int, TextSegment> $line
     * @return array<int, TextSegment>
     */
    private function appendEllipsisToLine(array $line, string $baseFont, int $size, float $maxWidth): array
    {
        $line = $this->trimTrailingWhitespaceFromLine($line);
        $ellipsisSegment = $this->buildEllipsisSegment($line, $baseFont);

        while ($line !== [] && $this->measureLineWidthWithSegment($line, $ellipsisSegment, $baseFont, $size) > $maxWidth) {
            $this->removeLastCharacterFromLine($line);
            $line = $this->trimTrailingWhitespaceFromLine($line);
            $ellipsisSegment = $this->buildEllipsisSegment($line, $baseFont);
        }

        while ($ellipsisSegment->text !== '' && $this->measureSegmentsWidth([$ellipsisSegment], $baseFont, $size) > $maxWidth) {
            $ellipsisSegment = new TextSegment(
                substr($ellipsisSegment->text, 0, -1),
                $ellipsisSegment->color,
                $ellipsisSegment->opacity,
                $ellipsisSegment->link,
                $ellipsisSegment->bold,
                $ellipsisSegment->italic,
                $ellipsisSegment->underline,
                $ellipsisSegment->strikethrough,
            );
        }

        if ($ellipsisSegment->text === '') {
            return $line;
        }

        $this->appendRun($line, $ellipsisSegment);

        return $line;
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function buildEllipsisSegment(array $line, string $baseFont): TextSegment
    {
        $lastIndex = array_key_last($line);

        if ($lastIndex === null) {
            return new TextSegment($this->resolveEllipsisText($baseFont, null));
        }

        $lastSegment = $line[$lastIndex];

        return new TextSegment(
            $this->resolveEllipsisText($baseFont, $lastSegment),
            $lastSegment->color,
            $lastSegment->opacity,
            $lastSegment->link,
            $lastSegment->bold,
            $lastSegment->italic,
            $lastSegment->underline,
            $lastSegment->strikethrough,
        );
    }

    private function resolveEllipsisText(string $baseFont, ?TextSegment $segment): string
    {
        $fontName = $segment === null
            ? $baseFont
            : $this->resolveStyledBaseFont($baseFont, $segment);

        return $this->resolveFont($fontName)->supportsText('…') ? '…' : '...';
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function removeLastCharacterFromLine(array &$line): void
    {
        while ($line !== []) {
            $lastIndex = array_key_last($line);
            $characters = preg_split('//u', $line[$lastIndex]->text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            array_pop($characters);
            $updatedText = implode('', $characters);

            if ($updatedText === '') {
                unset($line[$lastIndex]);
                $line = array_values($line);
                continue;
            }

            $this->replaceLastLineSegmentText($line, $updatedText);

            return;
        }
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function replaceLastLineSegmentText(array &$line, string $text): void
    {
        $lastIndex = array_key_last($line);
        assert(is_int($lastIndex));
        $lastSegment = $line[$lastIndex];
        $line[$lastIndex] = new TextSegment(
            $text,
            $lastSegment->color,
            $lastSegment->opacity,
            $lastSegment->link,
            $lastSegment->bold,
            $lastSegment->italic,
            $lastSegment->underline,
            $lastSegment->strikethrough,
        );
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function measureLineWidthWithSegment(array $line, TextSegment $segment, string $baseFont, int $size): float
    {
        $segments = $line;
        $this->appendRun($segments, $segment);

        return $this->measureSegmentsWidth($segments, $baseFont, $size);
    }

    /**
     * @param array<int, TextSegment> $segments
     */
    private function measureSegmentsWidth(array $segments, string $baseFont, int $size): float
    {
        $width = 0.0;

        foreach ($segments as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);
            $width += $segmentFont->measureTextWidth($segment->text, $size);
        }

        return $width;
    }

    /**
     * @return list<array{type: 'word', run: TextSegment}|array{type: 'space'}|array{type: 'newline'}>
     */
    private function tokenizeRun(TextSegment $run): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $run->text);
        /** @var list<array{type: 'word', run: TextSegment}|array{type: 'space'}|array{type: 'newline'}> $tokens */
        $tokens = [];
        $buffer = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($character === "\n") {
                if ($buffer !== '') {
                    $tokens[] = ['type' => 'word', 'run' => new TextSegment(
                        $buffer,
                        $run->color,
                        $run->opacity,
                        $run->link,
                        $run->bold,
                        $run->italic,
                        $run->underline,
                        $run->strikethrough,
                    )];
                    $buffer = '';
                }

                $tokens[] = ['type' => 'newline'];
                continue;
            }

            if (preg_match('/\s/u', $character) === 1) {
                if ($buffer !== '') {
                    $tokens[] = ['type' => 'word', 'run' => new TextSegment(
                        $buffer,
                        $run->color,
                        $run->opacity,
                        $run->link,
                        $run->bold,
                        $run->italic,
                        $run->underline,
                        $run->strikethrough,
                    )];
                    $buffer = '';
                }

                $tokens[] = ['type' => 'space'];
                continue;
            }

            $buffer .= $character;
        }

        if ($buffer !== '') {
            $tokens[] = ['type' => 'word', 'run' => new TextSegment(
                $buffer,
                $run->color,
                $run->opacity,
                $run->link,
                $run->bold,
                $run->italic,
                $run->underline,
                $run->strikethrough,
            )];
        }

        return $tokens;
    }

    /**
     * @param array<int, TextSegment> $runs
     */
    private function appendRun(array &$runs, TextSegment $run): void
    {
        $lastIndex = array_key_last($runs);

        if ($lastIndex === null) {
            $runs[] = $run;

            return;
        }

        $lastRun = $runs[$lastIndex];

        if (
            $lastRun->color === $run->color
            && $lastRun->opacity === $run->opacity
            && $this->linkTargetsEqual($lastRun->link, $run->link)
            && $lastRun->bold === $run->bold
            && $lastRun->italic === $run->italic
            && $lastRun->underline === $run->underline
            && $lastRun->strikethrough === $run->strikethrough
        ) {
            $runs[$lastIndex] = new TextSegment(
                $lastRun->text . $run->text,
                $lastRun->color,
                $lastRun->opacity,
                $lastRun->link,
                $lastRun->bold,
                $lastRun->italic,
                $lastRun->underline,
                $lastRun->strikethrough,
            );

            return;
        }

        $runs[] = $run;
    }

    private function linkTargetsEqual(?LinkTarget $left, ?LinkTarget $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left->equals($right);
    }

    private function resolveFont(string $baseFont): FontDefinition
    {
        return $this->fontResolver->resolveFont($baseFont);
    }

    private function resolveStyledBaseFont(string $baseFont, TextSegment $segment): string
    {
        return $this->fontResolver->resolveStyledBaseFont($baseFont, $segment);
    }
}
