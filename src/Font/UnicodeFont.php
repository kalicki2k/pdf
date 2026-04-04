<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;

final class UnicodeFont extends IndirectObject implements FontDefinition
{
    private const FALLBACK_GLYPH_WIDTH = 1000;

    public readonly UnicodeGlyphMap $glyphMap;

    public function __construct(
        int $id,
        public readonly CidFont $descendantFont,
        public readonly ToUnicodeCMap $toUnicode,
        ?UnicodeGlyphMap $glyphMap = null,
    ) {
        parent::__construct($id);

        $this->glyphMap = $glyphMap ?? new UnicodeGlyphMap();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBaseFont(): string
    {
        return $this->descendantFont->getBaseFont();
    }

    public function supportsText(string $text): bool
    {
        return mb_check_encoding($text, 'UTF-8');
    }

    public function encodeText(string $text): string
    {
        return $this->glyphMap->encodeText($text);
    }

    public function measureTextWidth(string $text, float $size): float
    {
        if ($text === '') {
            return 0.0;
        }

        if ($this->descendantFont->fontDescriptor === null) {
            return mb_strlen($text, 'UTF-8') * $size;
        }

        $fontParser = new OpenTypeFontParser($this->descendantFont->fontDescriptor->fontFile->data);
        $unitsPerEm = $fontParser->getUnitsPerEm();
        $width = 0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $glyphId = $fontParser->getGlyphIdForCharacter($character);
            $glyphWidth = $fontParser->getAdvanceWidthForGlyphId($glyphId);
            $width += $glyphWidth > 0 ? $glyphWidth : self::FALLBACK_GLYPH_WIDTH;
        }

        return ($width / $unitsPerEm) * $size;
    }

    /**
     * @return array<string, string>
     */
    public function getCodePointMap(): array
    {
        return $this->glyphMap->getCodePointMap();
    }

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Font'),
            'Subtype' => new Name('Type0'),
            'BaseFont' => new Name($this->getBaseFont()),
            'Encoding' => new Name('Identity-H'),
            'DescendantFonts' => new ArrayValue([
                new Reference($this->descendantFont),
            ]),
        ]);

        $dictionary->add('ToUnicode', new Reference($this->toUnicode));

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
