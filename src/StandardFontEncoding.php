<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use InvalidArgumentException;

enum StandardFontEncoding: string
{
    case STANDARD = 'StandardEncoding';
    case WIN_ANSI = 'WinAnsiEncoding';
    case SYMBOL = 'SymbolEncoding';
    case ZAPF_DINGBATS = 'ZapfDingbatsEncoding';

    /**
     * Common Unicode to SymbolEncoding byte mappings.
     *
     * Source: Adobe FrameMaker Symbol character set table.
     *
     * @var array<string, string>
     */
    private const SYMBOL_BYTE_MAP = [
        '∀' => "\x22",
        '∃' => "\x24",
        '∗' => "\x2A",
        '−' => "\x2D",
        '≅' => "\x40",
        'Α' => "\x41",
        'Β' => "\x42",
        'Χ' => "\x43",
        'Δ' => "\x44",
        'Ε' => "\x45",
        'Φ' => "\x46",
        'Γ' => "\x47",
        'Η' => "\x48",
        'Ι' => "\x49",
        'ϑ' => "\x4A",
        'Κ' => "\x4B",
        'Λ' => "\x4C",
        'Μ' => "\x4D",
        'Ν' => "\x4E",
        'Ο' => "\x4F",
        'Π' => "\x50",
        'Θ' => "\x51",
        'Ρ' => "\x52",
        'Σ' => "\x53",
        'Τ' => "\x54",
        'Υ' => "\x55",
        'ς' => "\x56",
        'Ω' => "\x57",
        'Ξ' => "\x58",
        'Ψ' => "\x59",
        'Ζ' => "\x5A",
        '∴' => "\x5C",
        '⊥' => "\x5E",
        'α' => "\x61",
        'β' => "\x62",
        'χ' => "\x63",
        'δ' => "\x64",
        'ε' => "\x65",
        'φ' => "\x66",
        'γ' => "\x67",
        'η' => "\x68",
        'ι' => "\x69",
        'ϕ' => "\x6A",
        'κ' => "\x6B",
        'λ' => "\x6C",
        'μ' => "\x6D",
        'ν' => "\x6E",
        'ο' => "\x6F",
        'π' => "\x70",
        'θ' => "\x71",
        'ρ' => "\x72",
        'σ' => "\x73",
        'τ' => "\x74",
        'υ' => "\x75",
        'ϖ' => "\x76",
        'ω' => "\x77",
        'ξ' => "\x78",
        'ψ' => "\x79",
        'ζ' => "\x7A",
        '∼' => "\x7E",
        'ϒ' => "\xA1",
        '′' => "\xA2",
        '≤' => "\xA3",
        '⁄' => "\xA4",
        '∞' => "\xA5",
        'ƒ' => "\xA6",
        '♣' => "\xA7",
        '♦' => "\xA8",
        '♥' => "\xA9",
        '♠' => "\xAA",
        '↔' => "\xAB",
        '←' => "\xAC",
        '↑' => "\xAD",
        '→' => "\xAE",
        '↓' => "\xAF",
        '°' => "\xB0",
        '±' => "\xB1",
        '″' => "\xB2",
        '≥' => "\xB3",
        '×' => "\xB4",
        '∝' => "\xB5",
        '∂' => "\xB6",
        '•' => "\xB7",
        '÷' => "\xB8",
        '≠' => "\xB9",
        '≡' => "\xBA",
        '≈' => "\xBB",
    ];

    /**
     * Common Unicode to ZapfDingbatsEncoding byte mappings.
     *
     * Source: Adobe FrameMaker ZapfDingbats character set table.
     *
     * @var array<string, string>
     */
    private const ZAPF_DINGBATS_BYTE_MAP = [
        '✁' => "\x21",
        '✂' => "\x22",
        '✃' => "\x23",
        '✄' => "\x24",
        '☎' => "\x25",
        '✆' => "\x26",
        '✈' => "\x28",
        '✉' => "\x29",
        '☛' => "\x2A",
        '☞' => "\x2B",
        '✌' => "\x2C",
        '✍' => "\x2D",
        '✎' => "\x2E",
        '✏' => "\x2F",
        '✐' => "\x30",
        '✑' => "\x31",
        '✒' => "\x32",
        '✓' => "\x33",
        '✔' => "\x34",
        '✕' => "\x35",
        '✖' => "\x36",
        '✗' => "\x37",
        '✘' => "\x38",
        '✙' => "\x39",
        '✚' => "\x3A",
        '✛' => "\x3B",
        '✜' => "\x3C",
        '✝' => "\x3D",
        '✞' => "\x3E",
        '✟' => "\x3F",
        '✠' => "\x40",
        '✡' => "\x41",
        '✢' => "\x42",
        '✣' => "\x43",
        '✤' => "\x44",
        '✥' => "\x45",
        '✦' => "\x46",
        '✧' => "\x47",
        '★' => "\x48",
        '✩' => "\x49",
        '✪' => "\x4A",
        '✫' => "\x4B",
        '✬' => "\x4C",
        '✭' => "\x4D",
        '✮' => "\x4E",
        '✯' => "\x4F",
        '✰' => "\x50",
        '✱' => "\x51",
        '✲' => "\x52",
        '✳' => "\x53",
        '✴' => "\x54",
        '✵' => "\x55",
        '✶' => "\x56",
        '✷' => "\x57",
        '✸' => "\x58",
        '✹' => "\x59",
        '✺' => "\x5A",
        '✻' => "\x5B",
        '✼' => "\x5C",
        '✽' => "\x5D",
        '✾' => "\x5E",
        '✿' => "\x5F",
        '❁' => "\x61",
        '❂' => "\x62",
        '❃' => "\x63",
        '❄' => "\x64",
        '❅' => "\x65",
        '❆' => "\x66",
        '❇' => "\x67",
        '❈' => "\x68",
        '❉' => "\x69",
        '❊' => "\x6A",
        '❋' => "\x6B",
        '●' => "\x6C",
        '❍' => "\x6D",
        '■' => "\x6E",
        '❏' => "\x6F",
        '❐' => "\x70",
        '❑' => "\x71",
        '❒' => "\x72",
        '▲' => "\x73",
        '▼' => "\x74",
        '◆' => "\x75",
        '❖' => "\x76",
        '❘' => "\x78",
        '❙' => "\x79",
        '❚' => "\x7A",
        '❡' => "\xA1",
        '❢' => "\xA2",
        '❣' => "\xA3",
        '❤' => "\xA4",
        '❥' => "\xA5",
        '❦' => "\xA6",
        '❧' => "\xA7",
        '①' => "\xAC",
        '②' => "\xAD",
        '③' => "\xAE",
        '④' => "\xAF",
        '⑤' => "\xB0",
        '⑥' => "\xB1",
        '⑦' => "\xB2",
        '⑧' => "\xB3",
        '⑨' => "\xB4",
        '⑩' => "\xB5",
        '❷' => "\xB7",
        '❸' => "\xB8",
        '❹' => "\xB9",
        '❺' => "\xBA",
    ];

    public static function forFont(string $fontName, float $pdfVersion): self
    {
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
            self::WIN_ANSI => $this->supportsWinAnsiText($text),
            self::STANDARD => $this->supportsAsciiText($text),
            self::SYMBOL => $this->supportsMappedText($text, self::SYMBOL_BYTE_MAP),
            self::ZAPF_DINGBATS => $this->supportsMappedText($text, self::ZAPF_DINGBATS_BYTE_MAP),
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
            self::WIN_ANSI => mb_convert_encoding($text, 'Windows-1252', 'UTF-8'),
            self::STANDARD => $text,
            self::SYMBOL => $this->encodeMappedText($text, self::SYMBOL_BYTE_MAP),
            self::ZAPF_DINGBATS => $this->encodeMappedText($text, self::ZAPF_DINGBATS_BYTE_MAP),
        };
    }

    private function supportsAsciiText(string $text): bool
    {
        return preg_match('/^[\x09\x0A\x0D\x20-\x7E]*$/', $text) === 1;
    }

    private function supportsWinAnsiText(string $text): bool
    {
        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        return $roundTrip === $text;
    }

    /**
     * @param array<string, string> $byteMap
     */
    private function supportsMappedText(string $text, array $byteMap): bool
    {
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
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
     * @param array<string, string> $byteMap
     */
    private function encodeMappedText(string $text, array $byteMap): string
    {
        $encoded = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
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
}
