<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use InvalidArgumentException;

final class UnicodeGlyphMap
{
    /** @var array<string, array{character: string, code: string}> */
    private array $entries = [];

    private int $nextCodePoint = 1;

    public function encodeText(string $text): string
    {
        $characters = mb_str_split($text);
        $encoded = '';

        foreach ($characters as $character) {
            $encoded .= $this->getOrAssignCode($character);
        }

        return '<' . $encoded . '>';
    }

    /**
     * @return array<string, string>
     */
    public function getCharacterMap(): array
    {
        $map = [];

        foreach ($this->entries as $entry) {
            $map[$entry['character']] = $entry['code'];
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public function getCodeMap(): array
    {
        $map = [];

        foreach ($this->entries as $entry) {
            $map[$entry['code']] = $entry['character'];
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public function getCodePointMap(): array
    {
        $map = [];

        foreach ($this->entries as $entry) {
            $map[$entry['code']] = strtoupper(bin2hex(mb_convert_encoding($entry['character'], 'UTF-16BE', 'UTF-8')));
        }

        return $map;
    }

    private function getOrAssignCode(string $character): string
    {
        $key = $this->getCharacterKey($character);

        if (isset($this->entries[$key])) {
            return $this->entries[$key]['code'];
        }

        if ($this->nextCodePoint > 0xFFFF) {
            throw new InvalidArgumentException('Unicode glyph map exhausted the available 16-bit code space.');
        }

        $code = strtoupper(sprintf('%04X', $this->nextCodePoint++));
        $this->entries[$key] = [
            'character' => $character,
            'code' => $code,
        ];

        return $code;
    }

    private function getCharacterKey(string $character): string
    {
        return strtoupper(bin2hex(mb_convert_encoding($character, 'UTF-16BE', 'UTF-8')));
    }
}
