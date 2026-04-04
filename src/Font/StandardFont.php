<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Utilities\PdfStringEscaper;

final class StandardFont extends IndirectObject implements FontDefinition
{
    private const FALLBACK_GLYPH_WIDTH = 600;

    public function __construct(
        int $id,
        public readonly string $baseFont,
        private readonly string $subtype,
        private readonly string $encoding,
        private readonly float $version,
        private readonly ?OpenTypeFontParser $fontParser = null,
    ) {
        parent::__construct($id);
        $this->validate();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBaseFont(): string
    {
        return $this->baseFont;
    }

    public function supportsText(string $text): bool
    {
        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        return $roundTrip === $text;
    }

    public function encodeText(string $text): string
    {
        if (!$this->supportsText($text)) {
            throw new InvalidArgumentException("Text cannot be encoded with font '$this->baseFont'.");
        }

        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return '(' . PdfStringEscaper::escape($encoded) . ')';
    }

    public function measureTextWidth(string $text, float $size): float
    {
        if ($text === '') {
            return 0.0;
        }

        if ($this->fontParser === null) {
            return strlen(mb_convert_encoding($text, 'Windows-1252', 'UTF-8')) * ($size * 0.6);
        }

        $unitsPerEm = $this->fontParser->getUnitsPerEm();
        $width = 0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $glyphId = $this->fontParser->getGlyphIdForCharacter($character);
            $glyphWidth = $this->fontParser->getAdvanceWidthForGlyphId($glyphId);
            $width += $glyphWidth > 0 ? $glyphWidth : self::FALLBACK_GLYPH_WIDTH;
        }

        return ($width / $unitsPerEm) * $size;
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Font'),
            'Subtype' => new NameType($this->subtype),
            'BaseFont' => new NameType($this->baseFont),
            'Encoding' => new NameType($this->encoding),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    private function validate(): void
    {
        $allowedEncodings10 = [
            'StandardEncoding',
            'ISOLatin1Encoding',
            'SymbolEncoding',
            'ZapfDingbatsEncoding',
        ];

        if (!$this->fontParser instanceof OpenTypeFontParser && !StandardFontName::isValid($this->baseFont)) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' is not a valid PDF standard font.");
        }

        if ($this->version === 1.0 && !in_array($this->encoding, $allowedEncodings10, true)) {
            throw new InvalidArgumentException("Encoding '$this->encoding' is not allowed in PDF 1.0.");
        }

        if ($this->encoding === 'SymbolEncoding' && $this->baseFont !== StandardFontName::SYMBOL) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' is not compatible with 'SymbolEncoding'.");
        }

        if ($this->encoding === 'ZapfDingbatsEncoding' && $this->baseFont !== StandardFontName::ZAPF_DINGBATS) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' is not compatible with 'ZapfDingbatsEncoding'.");
        }
    }
}
