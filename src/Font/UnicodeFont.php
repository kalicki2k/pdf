<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class UnicodeFont extends DictionaryIndirectObject implements FontDefinition
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

        $fontParser = $this->descendantFont->fontDescriptor->fontFile->parser();
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

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Font'),
            'Subtype' => new NameType('Type0'),
            'BaseFont' => new NameType($this->getBaseFont()),
            'Encoding' => new NameType('Identity-H'),
            'DescendantFonts' => new ArrayType([
                new ReferenceType($this->descendantFont),
            ]),
        ]);

        $dictionary->add('ToUnicode', new ReferenceType($this->toUnicode));

        return $dictionary;
    }
}
