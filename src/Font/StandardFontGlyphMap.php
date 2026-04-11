<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

final class StandardFontGlyphMap
{
    /**
     * @var array<string, string>
     */
    private const SYMBOL_NAME_TO_BYTE = [
        'space' => "\x20",
        'exclam' => "\x21",
        'universal' => "\x22",
        'numbersign' => "\x23",
        'existential' => "\x24",
        'percent' => "\x25",
        'ampersand' => "\x26",
        'suchthat' => "\x27",
        'parenleft' => "\x28",
        'parenright' => "\x29",
        'asteriskmath' => "\x2A",
        'plus' => "\x2B",
        'comma' => "\x2C",
        'minus' => "\x2D",
        'period' => "\x2E",
        'slash' => "\x2F",
        'zero' => "\x30",
        'one' => "\x31",
        'two' => "\x32",
        'three' => "\x33",
        'four' => "\x34",
        'five' => "\x35",
        'six' => "\x36",
        'seven' => "\x37",
        'eight' => "\x38",
        'nine' => "\x39",
        'colon' => "\x3A",
        'semicolon' => "\x3B",
        'less' => "\x3C",
        'equal' => "\x3D",
        'greater' => "\x3E",
        'question' => "\x3F",
        'congruent' => "\x40",
        'Alpha' => "\x41",
        'Beta' => "\x42",
        'Chi' => "\x43",
        'Delta' => "\x44",
        'Epsilon' => "\x45",
        'Phi' => "\x46",
        'Gamma' => "\x47",
        'Eta' => "\x48",
        'Iota' => "\x49",
        'theta1' => "\x4A",
        'Kappa' => "\x4B",
        'Lambda' => "\x4C",
        'Mu' => "\x4D",
        'Nu' => "\x4E",
        'Omicron' => "\x4F",
        'Pi' => "\x50",
        'Theta' => "\x51",
        'Rho' => "\x52",
        'Sigma' => "\x53",
        'Tau' => "\x54",
        'Upsilon' => "\x55",
        'sigma1' => "\x56",
        'Omega' => "\x57",
        'Xi' => "\x58",
        'Psi' => "\x59",
        'Zeta' => "\x5A",
        'bracketleft' => "\x5B",
        'therefore' => "\x5C",
        'bracketright' => "\x5D",
        'perpendicular' => "\x5E",
        'underscore' => "\x5F",
        'radicalex' => "\x60",
        'alpha' => "\x61",
        'beta' => "\x62",
        'chi' => "\x63",
        'delta' => "\x64",
        'epsilon' => "\x65",
        'phi' => "\x66",
        'gamma' => "\x67",
        'eta' => "\x68",
        'iota' => "\x69",
        'phi1' => "\x6A",
        'kappa' => "\x6B",
        'lambda' => "\x6C",
        'mu' => "\x6D",
        'nu' => "\x6E",
        'omicron' => "\x6F",
        'pi' => "\x70",
        'theta' => "\x71",
        'rho' => "\x72",
        'sigma' => "\x73",
        'tau' => "\x74",
        'upsilon' => "\x75",
        'omega1' => "\x76",
        'omega' => "\x77",
        'xi' => "\x78",
        'psi' => "\x79",
        'zeta' => "\x7A",
        'braceleft' => "\x7B",
        'bar' => "\x7C",
        'braceright' => "\x7D",
        'similar' => "\x7E",
        'Euro' => "\xA0",
        'Upsilon1' => "\xA1",
        'minute' => "\xA2",
        'lessequal' => "\xA3",
        'fraction' => "\xA4",
        'infinity' => "\xA5",
        'florin' => "\xA6",
        'club' => "\xA7",
        'diamond' => "\xA8",
        'heart' => "\xA9",
        'spade' => "\xAA",
        'arrowboth' => "\xAB",
        'arrowleft' => "\xAC",
        'arrowup' => "\xAD",
        'arrowright' => "\xAE",
        'arrowdown' => "\xAF",
        'degree' => "\xB0",
        'plusminus' => "\xB1",
        'second' => "\xB2",
        'greaterequal' => "\xB3",
        'multiply' => "\xB4",
        'proportional' => "\xB5",
        'partialdiff' => "\xB6",
        'bullet' => "\xB7",
        'divide' => "\xB8",
        'notequal' => "\xB9",
        'equivalence' => "\xBA",
        'approxequal' => "\xBB",
        'ellipsis' => "\xBC",
        'arrowvertex' => "\xBD",
        'arrowhorizex' => "\xBE",
        'carriagereturn' => "\xBF",
        'aleph' => "\xC0",
        'Ifraktur' => "\xC1",
        'Rfraktur' => "\xC2",
        'weierstrass' => "\xC3",
        'circlemultiply' => "\xC4",
        'circleplus' => "\xC5",
        'emptyset' => "\xC6",
        'intersection' => "\xC7",
        'union' => "\xC8",
        'propersuperset' => "\xC9",
        'reflexsuperset' => "\xCA",
        'notsubset' => "\xCB",
        'propersubset' => "\xCC",
        'reflexsubset' => "\xCD",
        'element' => "\xCE",
        'notelement' => "\xCF",
        'angle' => "\xD0",
        'gradient' => "\xD1",
        'registerserif' => "\xD2",
        'copyrightserif' => "\xD3",
        'trademarkserif' => "\xD4",
        'product' => "\xD5",
        'radical' => "\xD6",
        'dotmath' => "\xD7",
        'logicalnot' => "\xD8",
        'logicaland' => "\xD9",
        'logicalor' => "\xDA",
        'arrowdblboth' => "\xDB",
        'arrowdblleft' => "\xDC",
        'arrowdblup' => "\xDD",
        'arrowdblright' => "\xDE",
        'arrowdbldown' => "\xDF",
        'lozenge' => "\xE0",
        'angleleft' => "\xE1",
        'registersans' => "\xE2",
        'copyrightsans' => "\xE3",
        'trademarksans' => "\xE4",
        'summation' => "\xE5",
        'parenlefttp' => "\xE6",
        'parenleftex' => "\xE7",
        'parenleftbt' => "\xE8",
        'bracketlefttp' => "\xE9",
        'bracketleftex' => "\xEA",
        'bracketleftbt' => "\xEB",
        'bracelefttp' => "\xEC",
        'braceleftmid' => "\xED",
        'braceleftbt' => "\xEE",
        'braceex' => "\xEF",
        'angleright' => "\xF1",
        'integral' => "\xF2",
        'integraltp' => "\xF3",
        'integralex' => "\xF4",
        'integralbt' => "\xF5",
        'parenrighttp' => "\xF6",
        'parenrightex' => "\xF7",
        'parenrightbt' => "\xF8",
        'bracketrighttp' => "\xF9",
        'bracketrightex' => "\xFA",
        'bracketrightbt' => "\xFB",
        'bracerighttp' => "\xFC",
        'bracerightmid' => "\xFD",
        'bracerightbt' => "\xFE",
    ];

    /**
     * @var array<string, string>
     */
    private const ZAPF_DINGBATS_NAME_TO_BYTE = [
        'space' => "\x20",
        'a1' => "\x21",
        'a2' => "\x22",
        'a202' => "\x23",
        'a3' => "\x24",
        'a4' => "\x25",
        'a5' => "\x26",
        'a119' => "\x27",
        'a118' => "\x28",
        'a117' => "\x29",
        'a11' => "\x2A",
        'a12' => "\x2B",
        'a13' => "\x2C",
        'a14' => "\x2D",
        'a15' => "\x2E",
        'a16' => "\x2F",
        'a105' => "\x30",
        'a17' => "\x31",
        'a18' => "\x32",
        'a19' => "\x33",
        'a20' => "\x34",
        'a21' => "\x35",
        'a22' => "\x36",
        'a23' => "\x37",
        'a24' => "\x38",
        'a25' => "\x39",
        'a26' => "\x3A",
        'a27' => "\x3B",
        'a28' => "\x3C",
        'a6' => "\x3D",
        'a7' => "\x3E",
        'a8' => "\x3F",
        'a9' => "\x40",
        'a10' => "\x41",
        'a29' => "\x42",
        'a30' => "\x43",
        'a31' => "\x44",
        'a32' => "\x45",
        'a33' => "\x46",
        'a34' => "\x47",
        'a35' => "\x48",
        'a36' => "\x49",
        'a37' => "\x4A",
        'a38' => "\x4B",
        'a39' => "\x4C",
        'a40' => "\x4D",
        'a41' => "\x4E",
        'a42' => "\x4F",
        'a43' => "\x50",
        'a44' => "\x51",
        'a45' => "\x52",
        'a46' => "\x53",
        'a47' => "\x54",
        'a48' => "\x55",
        'a49' => "\x56",
        'a50' => "\x57",
        'a51' => "\x58",
        'a52' => "\x59",
        'a53' => "\x5A",
        'a54' => "\x5B",
        'a55' => "\x5C",
        'a56' => "\x5D",
        'a57' => "\x5E",
        'a58' => "\x5F",
        'a59' => "\x60",
        'a60' => "\x61",
        'a61' => "\x62",
        'a62' => "\x63",
        'a63' => "\x64",
        'a64' => "\x65",
        'a65' => "\x66",
        'a66' => "\x67",
        'a67' => "\x68",
        'a68' => "\x69",
        'a69' => "\x6A",
        'a70' => "\x6B",
        'a71' => "\x6C",
        'a72' => "\x6D",
        'a73' => "\x6E",
        'a74' => "\x6F",
        'a203' => "\x70",
        'a75' => "\x71",
        'a204' => "\x72",
        'a76' => "\x73",
        'a77' => "\x74",
        'a78' => "\x75",
        'a79' => "\x76",
        'a81' => "\x77",
        'a82' => "\x78",
        'a83' => "\x79",
        'a84' => "\x7A",
        'a97' => "\x7B",
        'a98' => "\x7C",
        'a99' => "\x7D",
        'a100' => "\x7E",
        'a89' => "\x80",
        'a90' => "\x81",
        'a93' => "\x82",
        'a94' => "\x83",
        'a91' => "\x84",
        'a92' => "\x85",
        'a205' => "\x86",
        'a85' => "\x87",
        'a206' => "\x88",
        'a86' => "\x89",
        'a87' => "\x8A",
        'a88' => "\x8B",
        'a95' => "\x8C",
        'a96' => "\x8D",
        'a101' => "\xA1",
        'a102' => "\xA2",
        'a103' => "\xA3",
        'a104' => "\xA4",
        'a106' => "\xA5",
        'a107' => "\xA6",
        'a108' => "\xA7",
        'a112' => "\xA8",
        'a111' => "\xA9",
        'a110' => "\xAA",
        'a109' => "\xAB",
        'a120' => "\xAC",
        'a121' => "\xAD",
        'a122' => "\xAE",
        'a123' => "\xAF",
        'a124' => "\xB0",
        'a125' => "\xB1",
        'a126' => "\xB2",
        'a127' => "\xB3",
        'a128' => "\xB4",
        'a129' => "\xB5",
        'a130' => "\xB6",
        'a131' => "\xB7",
        'a132' => "\xB8",
        'a133' => "\xB9",
        'a134' => "\xBA",
        'a135' => "\xBB",
        'a136' => "\xBC",
        'a137' => "\xBD",
        'a138' => "\xBE",
        'a139' => "\xBF",
        'a140' => "\xC0",
        'a141' => "\xC1",
        'a142' => "\xC2",
        'a143' => "\xC3",
        'a144' => "\xC4",
        'a145' => "\xC5",
        'a146' => "\xC6",
        'a147' => "\xC7",
        'a148' => "\xC8",
        'a149' => "\xC9",
        'a150' => "\xCA",
        'a151' => "\xCB",
        'a152' => "\xCC",
        'a153' => "\xCD",
        'a154' => "\xCE",
        'a155' => "\xCF",
        'a156' => "\xD0",
        'a157' => "\xD1",
        'a158' => "\xD2",
        'a159' => "\xD3",
        'a160' => "\xD4",
        'a161' => "\xD5",
        'a163' => "\xD6",
        'a164' => "\xD7",
        'a196' => "\xD8",
        'a165' => "\xD9",
        'a192' => "\xDA",
        'a166' => "\xDB",
        'a167' => "\xDC",
        'a168' => "\xDD",
        'a169' => "\xDE",
        'a170' => "\xDF",
        'a171' => "\xE0",
        'a172' => "\xE1",
        'a173' => "\xE2",
        'a162' => "\xE3",
        'a174' => "\xE4",
        'a175' => "\xE5",
        'a176' => "\xE6",
        'a177' => "\xE7",
        'a178' => "\xE8",
        'a179' => "\xE9",
        'a193' => "\xEA",
        'a180' => "\xEB",
        'a199' => "\xEC",
        'a181' => "\xED",
        'a200' => "\xEE",
        'a182' => "\xEF",
        'a201' => "\xF1",
        'a183' => "\xF2",
        'a184' => "\xF3",
        'a197' => "\xF4",
        'a185' => "\xF5",
        'a194' => "\xF6",
        'a198' => "\xF7",
        'a186' => "\xF8",
        'a195' => "\xF9",
        'a187' => "\xFA",
        'a188' => "\xFB",
        'a189' => "\xFC",
        'a190' => "\xFD",
        'a191' => "\xFE",
    ];

    /**
     * @return list<string>
     */
    public static function glyphNames(string | StandardFont $font): array
    {
        $fontName = self::fontName($font);

        if (isset(StandardFontCoreGlyphMap::NAME_TO_CODE[$fontName])) {
            return StandardFontCoreGlyphMap::glyphNames($fontName);
        }

        return array_keys(self::nameToByteMap($font));
    }

    public static function glyphCodeForName(string | StandardFont $font, string $glyphName): ?int
    {
        $fontName = self::fontName($font);

        if (isset(StandardFontCoreGlyphMap::NAME_TO_CODE[$fontName])) {
            $code = StandardFontCoreGlyphMap::glyphCode($fontName, $glyphName);

            return $code !== null && $code >= 0 ? $code : null;
        }

        $byte = self::nameToByteMap($font)[$glyphName] ?? null;

        return $byte === null ? null : ord($byte);
    }

    /**
     * @param list<string> $glyphNames
     * @return array{bytes: string, differences: array<int, string>, useHexString: bool}
     */
    public static function encodeGlyphNames(string | StandardFont $font, array $glyphNames): array
    {
        $fontName = self::fontName($font);

        if (isset(StandardFontCoreGlyphMap::NAME_TO_CODE[$fontName])) {
            return self::encodeCoreGlyphNames($fontName, $glyphNames);
        }

        $map = self::nameToByteMap($font);
        $encoded = '';

        foreach ($glyphNames as $glyphName) {
            $byte = $map[$glyphName] ?? null;

            if ($byte === null) {
                throw new InvalidArgumentException(sprintf(
                    "Glyph '%s' is not defined for font '%s'.",
                    $glyphName,
                    self::fontName($font),
                ));
            }

            $encoded .= $byte;
        }

        return [
            'bytes' => $encoded,
            'differences' => [],
            'useHexString' => false,
        ];
    }

    /**
     * @param list<int> $glyphCodes
     * @return array{bytes: string, differences: array<int, string>, useHexString: bool}
     */
    public static function encodeGlyphCodes(string | StandardFont $font, array $glyphCodes): array
    {
        $fontName = self::fontName($font);

        if (isset(StandardFontCoreGlyphMap::CODE_TO_NAME[$fontName])) {
            $encoded = '';

            foreach ($glyphCodes as $glyphCode) {
                if ($glyphCode < 0 || $glyphCode > 255 || StandardFontCoreGlyphMap::glyphNameForCode($fontName, $glyphCode) === null) {
                    throw new InvalidArgumentException(sprintf(
                        "Glyph code '%d' is not defined for font '%s'.",
                        $glyphCode,
                        $fontName,
                    ));
                }

                $encoded .= chr($glyphCode);
            }

            return [
                'bytes' => $encoded,
                'differences' => [],
                'useHexString' => true,
            ];
        }

        $map = self::nameToByteMap($font);
        $supportedCodes = array_flip(array_map('ord', array_values($map)));
        $encoded = '';

        foreach ($glyphCodes as $glyphCode) {
            if ($glyphCode < 0 || $glyphCode > 255 || !isset($supportedCodes[$glyphCode])) {
                throw new InvalidArgumentException(sprintf(
                    "Glyph code '%d' is not defined for font '%s'.",
                    $glyphCode,
                    self::fontName($font),
                ));
            }

            $encoded .= chr($glyphCode);
        }

        return [
            'bytes' => $encoded,
            'differences' => [],
            'useHexString' => true,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function nameToByteMap(string | StandardFont $font): array
    {
        $fontName = self::fontName($font);

        return match ($fontName) {
            StandardFont::SYMBOL->value => self::SYMBOL_NAME_TO_BYTE,
            StandardFont::ZAPF_DINGBATS->value => self::ZAPF_DINGBATS_NAME_TO_BYTE,
            default => throw new InvalidArgumentException(sprintf(
                "Explicit glyph access is only supported for '%s' and '%s', got '%s'.",
                StandardFont::SYMBOL->value,
                StandardFont::ZAPF_DINGBATS->value,
                $fontName,
            )),
        };
    }

    private static function fontName(string | StandardFont $font): string
    {
        return $font instanceof StandardFont
            ? $font->value
            : $font;
    }

    /**
     * @param list<string> $glyphNames
     * @return array{bytes: string, differences: array<int, string>, useHexString: bool}
     */
    private static function encodeCoreGlyphNames(string $fontName, array $glyphNames): array
    {
        $baseCodeToName = StandardFontCoreGlyphMap::CODE_TO_NAME[$fontName];
        $assigned = [];
        $bytes = '';

        foreach ($glyphNames as $glyphName) {
            $baseCode = StandardFontCoreGlyphMap::glyphCode($fontName, $glyphName);

            if ($baseCode === null) {
                throw new InvalidArgumentException(sprintf(
                    "Glyph '%s' is not defined for font '%s'.",
                    $glyphName,
                    $fontName,
                ));
            }

            $code = $baseCode >= 0 && !isset($assigned[$baseCode])
                ? $baseCode
                : self::allocateCoreGlyphCode($baseCodeToName, $assigned);

            if ($code < 0 || $code > 255) {
                throw new InvalidArgumentException(sprintf(
                    "Glyph '%s' could not be assigned a valid PDF byte code.",
                    $glyphName,
                ));
            }

            $assigned[$code] = $glyphName;
            $bytes .= chr($code);
        }

        $differences = [];

        foreach ($assigned as $code => $glyphName) {
            if (($baseCodeToName[$code] ?? null) !== $glyphName) {
                $differences[$code] = $glyphName;
            }
        }

        return [
            'bytes' => $bytes,
            'differences' => $differences,
            'useHexString' => true,
        ];
    }

    /**
     * @param array<int, string> $baseCodeToName
     * @param array<int, string> $assigned
     */
    private static function allocateCoreGlyphCode(array $baseCodeToName, array $assigned): int
    {
        for ($code = 128; $code <= 255; $code++) {
            if (!isset($assigned[$code]) && !isset($baseCodeToName[$code])) {
                return $code;
            }
        }

        for ($code = 0; $code <= 127; $code++) {
            if (!isset($assigned[$code]) && !isset($baseCodeToName[$code])) {
                return $code;
            }
        }

        for ($code = 128; $code <= 255; $code++) {
            if (!isset($assigned[$code])) {
                return $code;
            }
        }

        for ($code = 0; $code <= 127; $code++) {
            if (!isset($assigned[$code])) {
                return $code;
            }
        }

        throw new InvalidArgumentException('Too many unique glyphs requested for a single standard font run.');
    }
}
