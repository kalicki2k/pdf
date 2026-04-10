<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Font;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;
use Kalle\Pdf\Internal\PdfType\ReferenceType;
use Kalle\Pdf\PdfVersion;
use Kalle\Pdf\Utilities\PdfStringEscaper;

final class StandardFont extends DictionaryIndirectObject implements FontDefinition
{
    private const FALLBACK_GLYPH_WIDTH = 600;

    public function __construct(
        int $id,
        public readonly string $baseFont,
        private readonly string $subtype,
        private readonly string $encoding,
        private readonly float $version,
        private readonly ?OpenTypeFontParser $fontParser = null,
        public readonly ?EncodingDictionary $encodingDictionary = null,
        /** @var array<string, string> */
        private readonly array $byteMap = [],
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
        if ($this->byteMap !== []) {
            return $this->canEncodeWithByteMap($text);
        }

        $phpEncoding = $this->resolvePhpEncoding();

        if ($phpEncoding === null) {
            return $this->supportsAsciiOnlyText($text);
        }

        $encoded = mb_convert_encoding($text, $phpEncoding, 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', $phpEncoding);

        return $roundTrip === $text;
    }

    public function encodeText(string $text): string
    {
        if (!$this->supportsText($text)) {
            throw new InvalidArgumentException("Text cannot be encoded with font '$this->baseFont'.");
        }

        if ($this->byteMap !== []) {
            return '(' . PdfStringEscaper::escape($this->encodeWithByteMap($text)) . ')';
        }

        $phpEncoding = $this->resolvePhpEncoding();
        $encoded = $phpEncoding === null
            ? $text
            : mb_convert_encoding($text, $phpEncoding, 'UTF-8');

        return '(' . PdfStringEscaper::escape($encoded) . ')';
    }

    public function measureTextWidth(string $text, float $size): float
    {
        if ($text === '') {
            return 0.0;
        }

        if ($this->fontParser === null) {
            $encoded = $this->byteMap !== []
                ? $this->encodeWithByteMap($text)
                : $this->encodeTextForMeasurement($text);

            return StandardFontMetrics::measureTextWidth($this->baseFont, $encoded, $size)
                ?? strlen($encoded) * ($size * 0.6);
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

    protected function dictionary(): DictionaryType
    {
        return new DictionaryType([
            'Type' => new NameType('Font'),
            'Subtype' => new NameType($this->subtype),
            'BaseFont' => new NameType($this->baseFont),
            'Encoding' => $this->encodingDictionary instanceof EncodingDictionary
                ? new ReferenceType($this->encodingDictionary)
                : new NameType($this->encoding),
        ]);
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

        if ($this->version === PdfVersion::V1_0 && !in_array($this->encoding, $allowedEncodings10, true)) {
            throw new InvalidArgumentException("Encoding '$this->encoding' is not allowed in PDF 1.0.");
        }

        if ($this->encoding === 'SymbolEncoding' && $this->baseFont !== StandardFontName::SYMBOL) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' is not compatible with 'SymbolEncoding'.");
        }

        if ($this->encoding === 'ZapfDingbatsEncoding' && $this->baseFont !== StandardFontName::ZAPF_DINGBATS) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' is not compatible with 'ZapfDingbatsEncoding'.");
        }
    }

    private function resolvePhpEncoding(): ?string
    {
        return match ($this->encoding) {
            'WinAnsiEncoding' => 'Windows-1252',
            default => null,
        };
    }

    private function encodeTextForMeasurement(string $text): string
    {
        $phpEncoding = $this->resolvePhpEncoding();

        return $phpEncoding === null
            ? $text
            : mb_convert_encoding($text, $phpEncoding, 'UTF-8');
    }

    private function supportsAsciiOnlyText(string $text): bool
    {
        return preg_match('/^[\x09\x0A\x0D\x20-\x7E]*$/', $text) === 1;
    }

    private function canEncodeWithByteMap(string $text): bool
    {
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($this->isAsciiCompatibleCharacter($character)) {
                continue;
            }

            if (array_key_exists($character, $this->byteMap)) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function encodeWithByteMap(string $text): string
    {
        $encoded = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($this->isAsciiCompatibleCharacter($character)) {
                $encoded .= $character;
                continue;
            }

            $mappedByte = $this->byteMap[$character] ?? null;

            if ($mappedByte === null) {
                throw new InvalidArgumentException("Text cannot be encoded with font '$this->baseFont'.");
            }

            $encoded .= $mappedByte;
        }

        return $encoded;
    }

    private function isAsciiCompatibleCharacter(string $character): bool
    {
        return preg_match('/^[\x09\x0A\x0D\x20-\x7E]$/', $character) === 1;
    }
}
