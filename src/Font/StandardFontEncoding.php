<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;
use Kalle\Pdf\Document\Version;

enum StandardFontEncoding: string
{
    case STANDARD = 'StandardEncoding';
    case ISO_LATIN_1 = 'ISOLatin1Encoding';
    case WIN_ANSI = 'WinAnsiEncoding';
    case SYMBOL = 'SymbolEncoding';
    case ZAPF_DINGBATS = 'ZapfDingbatsEncoding';

    /**
     * @var array<int, string>
     */
    private const WESTERN_STANDARD_DIFFERENCES = [
        128 => 'Adieresis',
        129 => 'Aring',
        130 => 'Ccedilla',
        131 => 'Eacute',
        132 => 'Ntilde',
        133 => 'Odieresis',
        134 => 'Udieresis',
        135 => 'aacute',
        136 => 'agrave',
        137 => 'acircumflex',
        138 => 'adieresis',
        139 => 'atilde',
        140 => 'aring',
        141 => 'ccedilla',
        142 => 'eacute',
        143 => 'egrave',
        144 => 'ecircumflex',
        145 => 'edieresis',
        146 => 'iacute',
        147 => 'igrave',
        148 => 'icircumflex',
        149 => 'idieresis',
        150 => 'ntilde',
        151 => 'oacute',
        152 => 'ograve',
        153 => 'ocircumflex',
        154 => 'odieresis',
        155 => 'otilde',
        156 => 'uacute',
        157 => 'ugrave',
        158 => 'ucircumflex',
        159 => 'udieresis',
        160 => 'dagger',
        161 => 'degree',
        162 => 'cent',
        163 => 'sterling',
        164 => 'section',
        165 => 'bullet',
        166 => 'paragraph',
        167 => 'germandbls',
        168 => 'registered',
        169 => 'copyright',
        170 => 'trademark',
        171 => 'acute',
        172 => 'dieresis',
        174 => 'AE',
        175 => 'Oslash',
        177 => 'plusminus',
        180 => 'yen',
        181 => 'mu',
        187 => 'ordfeminine',
        188 => 'ordmasculine',
        190 => 'ae',
        191 => 'oslash',
    ];

    /**
     * @var array<string, string>
     */
    private const WESTERN_STANDARD_BYTE_MAP = [
        'Ä' => "\x80",
        'Å' => "\x81",
        'Ç' => "\x82",
        'É' => "\x83",
        'Ñ' => "\x84",
        'Ö' => "\x85",
        'Ü' => "\x86",
        'á' => "\x87",
        'à' => "\x88",
        'â' => "\x89",
        'ä' => "\x8A",
        'ã' => "\x8B",
        'å' => "\x8C",
        'ç' => "\x8D",
        'é' => "\x8E",
        'è' => "\x8F",
        'ê' => "\x90",
        'ë' => "\x91",
        'í' => "\x92",
        'ì' => "\x93",
        'î' => "\x94",
        'ï' => "\x95",
        'ñ' => "\x96",
        'ó' => "\x97",
        'ò' => "\x98",
        'ô' => "\x99",
        'ö' => "\x9A",
        'õ' => "\x9B",
        'ú' => "\x9C",
        'ù' => "\x9D",
        'û' => "\x9E",
        'ü' => "\x9F",
        '†' => "\xA0",
        '°' => "\xA1",
        '¢' => "\xA2",
        '£' => "\xA3",
        '§' => "\xA4",
        '•' => "\xA5",
        '¶' => "\xA6",
        'ß' => "\xA7",
        '®' => "\xA8",
        '©' => "\xA9",
        '™' => "\xAA",
        '´' => "\xAB",
        '¨' => "\xAC",
        'Æ' => "\xAE",
        'Ø' => "\xAF",
        '±' => "\xB1",
        '¥' => "\xB4",
        'µ' => "\xB5",
        'ª' => "\xBB",
        'º' => "\xBC",
        'æ' => "\xBE",
        'ø' => "\xBF",
    ];

    public static function forFont(string $fontName, float $pdfVersion, ?self $preferredEncoding = null): self
    {
        if ($preferredEncoding !== null) {
            self::assertCompatibleWithFont($preferredEncoding, $fontName);
            self::assertAllowedForVersion($preferredEncoding, $pdfVersion);

            return $preferredEncoding;
        }

        return match ($fontName) {
            StandardFont::SYMBOL->value => self::SYMBOL,
            StandardFont::ZAPF_DINGBATS->value => self::ZAPF_DINGBATS,
            default => $pdfVersion === Version::V1_0
                ? self::STANDARD
                : self::WIN_ANSI,
        };
    }

    public function supportsText(string $text): bool
    {
        return match ($this) {
            self::ISO_LATIN_1 => $this->supportsIsoLatin1Text($text),
            self::WIN_ANSI => $this->supportsWinAnsiText($text),
            self::STANDARD => $this->supportsStandardText($text),
            self::SYMBOL => $this->supportsMappedText($text, StandardFontSymbolMap::MAP),
            self::ZAPF_DINGBATS => $this->supportsMappedText($text, StandardFontZapfDingbatsMap::MAP),
        };
    }

    public function encodeText(string $text): string
    {
        if (!$this->supportsText($text)) {
            throw new InvalidArgumentException(sprintf(
                "Text cannot be encoded with '%s'.",
                $this->value,
            ));
        }

        return match ($this) {
            self::ISO_LATIN_1 => mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8'),
            self::WIN_ANSI => mb_convert_encoding($text, 'Windows-1252', 'UTF-8'),
            self::STANDARD => $this->encodeStandardText($text),
            self::SYMBOL => $this->encodeMappedText($text, StandardFontSymbolMap::MAP),
            self::ZAPF_DINGBATS => $this->encodeMappedText($text, StandardFontZapfDingbatsMap::MAP),
        };
    }

    public function pdfObjectValue(string $fontName): string
    {
        if ($this !== self::STANDARD || in_array($fontName, [StandardFont::SYMBOL->value, StandardFont::ZAPF_DINGBATS->value], true)) {
            return '/' . $this->value;
        }

        return '<< /Type /Encoding /BaseEncoding /StandardEncoding /Differences ['
            . $this->differencesPdfEntries(self::WESTERN_STANDARD_DIFFERENCES)
            . '] >>';
    }

    /**
     * @param array<int, string> $differences
     */
    public function pdfObjectValueWithDifferences(string $fontName, array $differences): string
    {
        if ($differences === []) {
            return $this->pdfObjectValue($fontName);
        }

        if (in_array($this, [self::SYMBOL, self::ZAPF_DINGBATS], true)) {
            return '/' . $this->value;
        }

        if ($this === self::STANDARD) {
            $mergedDifferences = [...self::WESTERN_STANDARD_DIFFERENCES, ...$differences];
            ksort($mergedDifferences);

            return '<< /Type /Encoding /BaseEncoding /StandardEncoding /Differences ['
                . $this->differencesPdfEntries($mergedDifferences)
                . '] >>';
        }

        ksort($differences);

        return '<< /Type /Encoding /BaseEncoding /' . $this->value . ' /Differences ['
            . $this->differencesPdfEntries($differences)
            . '] >>';
    }

    private function supportsStandardText(string $text): bool
    {
        foreach ($this->characters($text) as $character) {
            if ($this->isAsciiCompatibleCharacter($character)) {
                continue;
            }

            if (array_key_exists($character, self::WESTERN_STANDARD_BYTE_MAP)) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function encodeStandardText(string $text): string
    {
        $encoded = '';

        foreach ($this->characters($text) as $character) {
            if ($this->isAsciiCompatibleCharacter($character)) {
                $encoded .= $character;

                continue;
            }

            $encoded .= self::WESTERN_STANDARD_BYTE_MAP[$character];
        }

        return $encoded;
    }

    private function supportsWinAnsiText(string $text): bool
    {
        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        return $roundTrip === $text;
    }

    private function supportsIsoLatin1Text(string $text): bool
    {
        $encoded = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'ISO-8859-1');

        return $roundTrip === $text;
    }

    /**
     * @param array<array-key, string> $byteMap
     */
    private function supportsMappedText(string $text, array $byteMap): bool
    {
        foreach ($this->characters($text) as $character) {
            if ($this->isAsciiWhitespaceCharacter($character)) {
                continue;
            }

            if (!array_key_exists($character, $byteMap)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, string> $byteMap
     */
    private function encodeMappedText(string $text, array $byteMap): string
    {
        $encoded = '';

        foreach ($this->characters($text) as $character) {
            if ($this->isAsciiWhitespaceCharacter($character)) {
                $encoded .= $character;

                continue;
            }

            $encoded .= $byteMap[$character];
        }

        return $encoded;
    }

    private function isAsciiWhitespaceCharacter(string $character): bool
    {
        return preg_match('/^[\x09\x0A\x0D\x20]$/', $character) === 1;
    }

    private function isAsciiCompatibleCharacter(string $character): bool
    {
        return preg_match('/^[\x09\x0A\x0D\x20-\x7E]$/', $character) === 1;
    }

    /**
     * @param array<int, string> $differences
     */
    private function differencesPdfEntries(array $differences): string
    {
        $parts = [];
        $currentCode = null;

        foreach ($differences as $code => $glyphName) {
            if ($currentCode === null || $code !== $currentCode + 1) {
                $parts[] = (string) $code;
            }

            $parts[] = '/' . $glyphName;
            $currentCode = $code;
        }

        return implode(' ', $parts);
    }

    /**
     * @return list<string>
     */
    private function characters(string $text): array
    {
        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text);
    }

    private static function assertCompatibleWithFont(self $encoding, string $fontName): void
    {
        if ($encoding === self::SYMBOL && $fontName !== StandardFont::SYMBOL->value) {
            throw new InvalidArgumentException(sprintf(
                "Encoding '%s' is not compatible with font '%s'.",
                $encoding->value,
                $fontName,
            ));
        }

        if ($encoding === self::ZAPF_DINGBATS && $fontName !== StandardFont::ZAPF_DINGBATS->value) {
            throw new InvalidArgumentException(sprintf(
                "Encoding '%s' is not compatible with font '%s'.",
                $encoding->value,
                $fontName,
            ));
        }

        if (in_array($encoding, [self::STANDARD, self::ISO_LATIN_1, self::WIN_ANSI], true)
            && in_array($fontName, [StandardFont::SYMBOL->value, StandardFont::ZAPF_DINGBATS->value], true)
        ) {
            throw new InvalidArgumentException(sprintf(
                "Encoding '%s' is not compatible with font '%s'.",
                $encoding->value,
                $fontName,
            ));
        }
    }

    private static function assertAllowedForVersion(self $encoding, float $pdfVersion): void
    {
        if ($pdfVersion === Version::V1_0 && $encoding === self::WIN_ANSI) {
            throw new InvalidArgumentException('Encoding \'WinAnsiEncoding\' requires PDF 1.1 or higher.');
        }
    }
}
