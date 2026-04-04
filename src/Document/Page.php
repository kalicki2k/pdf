<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Element\Text;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\OpenTypeFontParser;
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
    ): self {
        if ($tag !== null) {
            $this->document->ensureStructureEnabled();
        }

        $font = $this->resolveFont($baseFont);
        $markedContentId = $tag !== null ? $this->markedContentId++ : null;
        $encodedText = $this->encodeText($font, $baseFont, $text);
        $resourceFontName = $this->registerFontResource($font);
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
            $colorOperator,
            $graphicsStateName,
            $tag,
        ));

        if ($tag !== null && $markedContentId !== null) {
            $this->attachTextToStructure($tag, $markedContentId);
        }

        return $this;
    }

    public function addParagraph(
        string $text,
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
        $font = $this->resolveFont($baseFont);
        $lineHeight ??= $size * self::DEFAULT_LINE_HEIGHT_FACTOR;
        $bottomMargin ??= self::DEFAULT_BOTTOM_MARGIN;

        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        $lines = $this->wrapTextIntoLines($text, $font, $size, $maxWidth);
        $page = $this;
        $currentY = $y;
        $topMargin = $this->height - $y;

        foreach ($lines as $line) {
            if ($currentY < $bottomMargin) {
                $page = $this->document->addPage($this->width, $this->height);
                $currentY = $this->height - $topMargin;
            }

            if ($line === '') {
                $currentY -= $lineHeight;
                continue;
            }

            $page->addText($line, $x, $currentY, $baseFont, $size, $tag, $color, $opacity);
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
    private function wrapTextIntoLines(string $text, FontDefinition $font, int $size, float $maxWidth): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));

        if ($text === '') {
            return [''];
        }

        $lines = [];

        foreach (explode("\n", $text) as $paragraph) {
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $currentLine = '';

            foreach (preg_split('/\s+/u', trim($paragraph)) ?: [] as $word) {
                if ($word === '') {
                    continue;
                }

                $candidate = $currentLine === '' ? $word : $currentLine . ' ' . $word;

                if ($font->measureTextWidth($candidate, $size) <= $maxWidth) {
                    $currentLine = $candidate;
                    continue;
                }

                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }

                $chunks = $this->breakWordToFit($word, $font, $size, $maxWidth);

                foreach ($chunks as $index => $chunk) {
                    if ($index === count($chunks) - 1) {
                        $currentLine = $chunk;
                        continue;
                    }

                    $lines[] = $chunk;
                }
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    public function countParagraphLines(string $text, string $baseFont, int $size, float $maxWidth): int
    {
        $font = $this->resolveFont($baseFont);

        return count($this->wrapTextIntoLines($text, $font, $size, $maxWidth));
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
}
