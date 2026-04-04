<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Element\Text;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\FontRegistry;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;

final class Page extends IndirectObject
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const DEFAULT_BOTTOM_MARGIN = 20.0;

    private int $markedContentId = 0;
    public Contents $contents;
    public Resources $resources;

    public function __construct(
        public int                $id,
        int                       $contentsId,
        int                       $resourcesId,
        public readonly int       $structParentId,
        private readonly float    $width,
        private readonly float    $height,
        private readonly Document $document,
    ) {
        parent::__construct($this->id);

        $this->contents = new Contents($contentsId);
        $this->resources = new Resources($resourcesId);
    }

    public function addText(
        string $text,
        float $x,
        float $y,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
        bool $underline = false,
        bool $strikethrough = false,
    ): self {
        if ($tag !== null) {
            $this->document->ensureStructureEnabled();
        }

        $font = $this->resolveFont($baseFont);
        $markedContentId = $tag !== null ? $this->markedContentId++ : null;
        $encodedText = $this->encodeText($font, $baseFont, $text);
        $resourceFontName = $this->registerFontResource($font);
        $textWidth = $font->measureTextWidth($text, $size);
        $colorOperator = $color?->renderNonStrokingOperator();
        $graphicsStateName = $opacity !== null ? $this->resources->addOpacity($opacity) : null;

        $this->updateUnicodeFontWidths($font);

        $this->contents->addElement(new Text(
            $markedContentId,
            $encodedText,
            $x,
            $y,
            $resourceFontName,
            $size,
            $textWidth,
            $colorOperator,
            $graphicsStateName,
            $underline,
            $strikethrough,
            $tag,
        ));

        if ($tag !== null && $markedContentId !== null) {
            $this->attachTextToStructure($tag, $markedContentId);
        }

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addParagraph(
        string|array $text,
        float $x,
        float $y,
        float $maxWidth,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $lineHeight = null,
        ?float $bottomMargin = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
    ): self {
        $lineHeight ??= $size * self::DEFAULT_LINE_HEIGHT_FACTOR;
        $bottomMargin ??= self::DEFAULT_BOTTOM_MARGIN;

        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        $runs = $this->normalizeTextRuns($text, $color, $opacity);
        $lines = $this->wrapRunsIntoLines($runs, $baseFont, $size, $maxWidth);
        $page = $this;
        $currentY = $y;
        $topMargin = $this->height - $y;

        foreach ($lines as $line) {
            if ($currentY < $bottomMargin) {
                $page = $this->document->addPage($this->width, $this->height);
                $currentY = $this->height - $topMargin;
            }

            if ($line === []) {
                $currentY -= $lineHeight;
                continue;
            }

            $cursorX = $x;

            foreach ($line as $segment) {
                $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
                $segmentFont = $this->resolveFont($segmentFontName);

                $page->addText(
                    $segment->text,
                    $cursorX,
                    $currentY,
                    $segmentFontName,
                    $size,
                    $tag,
                    $segment->color,
                    $segment->opacity,
                    $segment->underline,
                    $segment->strikethrough,
                );
                $cursorX += $segmentFont->measureTextWidth($segment->text, $size);
            }

            $currentY -= $lineHeight;
        }

        return $page;
    }

    public function textFrame(
        float $x,
        float $y,
        float $width,
        float $bottomMargin = self::DEFAULT_BOTTOM_MARGIN,
    ): TextFrame {
        return new TextFrame($this, $x, $y, $width, $bottomMargin);
    }

    public function addImage(): self
    {
        return $this;
    }

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Page'),
            'Parent' => new Reference($this->document->pages),
            'MediaBox' => new ArrayValue([0, 0, $this->width, $this->height]),
            'Resources' => new Reference($this->resources),
            'Contents' => new Reference($this->contents),
        ]);

        if ($this->markedContentId > 0 && $this->document->hasStructure()) {
            $dictionary->add('StructParents', $this->structParentId);
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    private function resolveFont(string $baseFont): FontDefinition
    {
        foreach ($this->document->fonts as $registeredFont) {
            if ($registeredFont->getBaseFont() === $baseFont) {
                return $registeredFont;
            }
        }

        throw new InvalidArgumentException("Font '$baseFont' is not registered.");
    }

    private function encodeText(FontDefinition $font, string $baseFont, string $text): string
    {
        if (!$font->supportsText($text)) {
            throw new InvalidArgumentException("Font '$baseFont' does not support the provided text.");
        }

        return $font->encodeText($text);
    }

    private function attachTextToStructure(string $tag, int $markedContentId): void
    {
        $this->document->addStructElem($tag, $markedContentId, $this);
    }

    private function registerFontResource(FontDefinition $font): string
    {
        return $this->resources->addFont($font);
    }

    private function updateUnicodeFontWidths(FontDefinition $font): void
    {
        if (
            !$font instanceof UnicodeFont
            || $font->descendantFont->cidToGidMap === null
            || $font->descendantFont->fontDescriptor === null
        ) {
            return;
        }

        $fontParser = new OpenTypeFontParser($font->descendantFont->fontDescriptor->fontFile->data);
        $widths = [];

        foreach ($font->getCodePointMap() as $cid => $codePointHex) {
            $utf16 = hex2bin($codePointHex);

            if ($utf16 === false) {
                throw new InvalidArgumentException("Invalid UTF-16 hex code point '$codePointHex'.");
            }

            $character = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
            $glyphId = $fontParser->getGlyphIdForCharacter($character);
            $widths[$cid] = $fontParser->getAdvanceWidthForGlyphId($glyphId);
        }

        $font->descendantFont->setWidths($widths);
    }

    /**
     * @return list<string>
     */
    /**
     * @param string|list<TextSegment> $text
     */
    public function countParagraphLines(string|array $text, string $baseFont, int $size, float $maxWidth): int
    {
        return count($this->wrapRunsIntoLines($this->normalizeTextRuns($text, null, null), $baseFont, $size, $maxWidth));
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
    private function normalizeTextRuns(string|array $text, ?Color $color, ?Opacity $opacity): array
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
     * @return array<int, array<int, TextSegment>>
     */
    private function wrapRunsIntoLines(array $runs, string $baseFont, int $size, float $maxWidth): array
    {
        /** @var array<int, array<int, TextSegment>> $lines */
        $lines = [];
        /** @var list<TextSegment> $currentLine */
        $currentLine = [];
        $currentLineWidth = 0.0;
        $pendingSpace = false;

        foreach ($runs as $run) {
            foreach ($this->tokenizeRun($run) as $token) {
                if ($token['type'] === 'newline') {
                    $lines[] = $currentLine;
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
                    $lines[] = $currentLine;
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
                            $wordRun->bold,
                            $wordRun->italic,
                            $wordRun->underline,
                            $wordRun->strikethrough,
                        )];
                        $currentLineWidth = $wordFont->measureTextWidth($chunk, $size);
                        continue;
                    }

                    $lines[] = [new TextSegment(
                        $chunk,
                        $wordRun->color,
                        $wordRun->opacity,
                        $wordRun->bold,
                        $wordRun->italic,
                        $wordRun->underline,
                        $wordRun->strikethrough,
                    )];
                }
            }
        }

        if ($currentLine !== []) {
            $lines[] = $currentLine;
        }

        return $lines === [] ? [[]] : $lines;
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
            && $lastRun->bold === $run->bold
            && $lastRun->italic === $run->italic
            && $lastRun->underline === $run->underline
            && $lastRun->strikethrough === $run->strikethrough
        ) {
            $runs[$lastIndex] = new TextSegment(
                $lastRun->text . $run->text,
                $lastRun->color,
                $lastRun->opacity,
                $lastRun->bold,
                $lastRun->italic,
                $lastRun->underline,
                $lastRun->strikethrough,
            );
            return;
        }

        $runs[] = $run;
    }

    private function resolveStyledBaseFont(string $baseFont, TextSegment $segment): string
    {
        if (!$segment->bold && !$segment->italic) {
            return $baseFont;
        }

        $standardVariant = StandardFontName::resolveVariant($baseFont, $segment->bold, $segment->italic);

        if ($standardVariant !== null) {
            $this->registerFontIfNeeded($standardVariant);

            return $standardVariant;
        }

        foreach ($this->buildVariantCandidates($baseFont, $segment->bold, $segment->italic) as $candidate) {
            if ($candidate === $baseFont) {
                continue;
            }

            if ($this->hasRegisteredFont($candidate) || FontRegistry::has($candidate, $this->document->getFontConfig())) {
                $this->registerFontIfNeeded($candidate);

                return $candidate;
            }
        }

        return $baseFont;
    }

    /**
     * @return list<string>
     */
    private function buildVariantCandidates(string $baseFont, bool $bold, bool $italic): array
    {
        if (!$bold && !$italic) {
            return [$baseFont];
        }

        if ($bold && $italic) {
            $suffix = ['BoldItalic', 'BoldOblique'];
        } elseif ($bold) {
            $suffix = ['Bold'];
        } else {
            $suffix = ['Italic', 'Oblique'];
        }

        $candidates = [];

        foreach ($suffix as $variantSuffix) {
            if (str_ends_with($baseFont, '-Regular')) {
                $candidates[] = substr($baseFont, 0, -strlen('-Regular')) . '-' . $variantSuffix;
                continue;
            }

            if (str_ends_with($baseFont, '-Roman')) {
                $candidates[] = substr($baseFont, 0, -strlen('-Roman')) . '-' . $variantSuffix;
                continue;
            }

            $candidates[] = $baseFont . '-' . $variantSuffix;
        }

        return array_values(array_unique($candidates));
    }

    private function hasRegisteredFont(string $baseFont): bool
    {
        foreach ($this->document->fonts as $registeredFont) {
            if ($registeredFont->getBaseFont() === $baseFont) {
                return true;
            }
        }

        return false;
    }

    private function registerFontIfNeeded(string $baseFont): void
    {
        if ($this->hasRegisteredFont($baseFont)) {
            return;
        }

        $this->document->addFont($baseFont);
    }
}
