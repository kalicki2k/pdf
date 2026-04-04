<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Element\Text;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;

final class Page extends IndirectObject
{
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

    public function addText(string $text, float $x, float $y, string $baseFont, int $size, string $tag): self
    {
        $font = $this->resolveFont($baseFont);
        $markedContentId = $this->markedContentId++;
        $encodedText = $this->encodeText($font, $baseFont, $text);
        $resourceFontName = $this->registerFontResource($font);

        $this->updateUnicodeFontWidths($font);

        $this->contents->addElement(new Text(
            $markedContentId,
            $encodedText,
            $x,
            $y,
            $resourceFontName,
            $size,
            $tag,
        ));
        $this->attachTextToStructure($tag, $markedContentId);

        return $this;
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
            'StructParents' => $this->structParentId,
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
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
}
