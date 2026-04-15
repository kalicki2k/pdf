<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function chr;
use function mb_convert_encoding;
use function mb_ord;
use function preg_split;
use function sprintf;
use function str_split;

/**
 * Encodes PDF text strings according to ISO 32000 text string rules:
 * PDFDocEncoding when possible, otherwise UTF-16BE with BOM.
 */
final class PdfTextStringEncoder
{
    /**
     * @var array<int, int>|null
     */
    private static ?array $unicodeToPdfDocEncoding = null;

    public function encodeLiteral(string $value): string
    {
        return '(' . $this->encodeLiteralBytes($this->encodeTextStringBytes($value)) . ')';
    }

    public function encodeTextStringBytes(string $value): string
    {
        $pdfDocEncodingBytes = $this->tryEncodePdfDocEncoding($value);

        if ($pdfDocEncodingBytes !== null) {
            return $pdfDocEncodingBytes;
        }

        return "\xFE\xFF" . mb_convert_encoding($value, 'UTF-16BE', 'UTF-8');
    }

    public function encodeLiteralBytes(string $bytes): string
    {
        $encoded = '';

        foreach (str_split($bytes) as $byte) {
            $value = ord($byte[0]);

            $encoded .= match (true) {
                $byte === '\\' => '\\\\',
                $byte === '(' => '\(',
                $byte === ')' => '\)',
                $value >= 32 && $value <= 126 => $byte,
                default => sprintf('\\%03o', $value),
            };
        }

        return $encoded;
    }

    private function tryEncodePdfDocEncoding(string $value): ?string
    {
        $bytes = '';

        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $codePoint = mb_ord($character, 'UTF-8');
            $pdfDocEncoding = self::unicodeToPdfDocEncoding()[$codePoint] ?? null;

            if ($pdfDocEncoding === null) {
                return null;
            }

            /** @var int<0, 255> $pdfDocEncoding */
            $bytes .= chr($pdfDocEncoding);
        }

        return $bytes;
    }

    /**
     * @return array<int, int>
     */
    private static function unicodeToPdfDocEncoding(): array
    {
        if (self::$unicodeToPdfDocEncoding !== null) {
            return self::$unicodeToPdfDocEncoding;
        }

        $map = [];

        for ($code = 0; $code <= 0xFF; $code++) {
            if (($code > 0x17 && $code < 0x20) || ($code > 0x7E && $code < 0xA1) || $code === 0xAD) {
                continue;
            }

            $map[$code] = $code;
        }

        foreach (self::pdfDocEncodingDeviations() as $byte => $codePoint) {
            if ($codePoint === null) {
                continue;
            }

            $map[$codePoint] = $byte;
        }

        self::$unicodeToPdfDocEncoding = $map;

        return $map;
    }

    /**
     * @return array<int, int|null>
     */
    private static function pdfDocEncodingDeviations(): array
    {
        return [
            0x18 => 0x02D8,
            0x19 => 0x02C7,
            0x1A => 0x02C6,
            0x1B => 0x02D9,
            0x1C => 0x02DD,
            0x1D => 0x02DB,
            0x1E => 0x02DA,
            0x1F => 0x02DC,
            0x7F => null,
            0x80 => 0x2022,
            0x81 => 0x2020,
            0x82 => 0x2021,
            0x83 => 0x2026,
            0x84 => 0x2014,
            0x85 => 0x2013,
            0x86 => 0x0192,
            0x87 => 0x2044,
            0x88 => 0x2039,
            0x89 => 0x203A,
            0x8A => 0x2212,
            0x8B => 0x2030,
            0x8C => 0x201E,
            0x8D => 0x201C,
            0x8E => 0x201D,
            0x8F => 0x2018,
            0x90 => 0x2019,
            0x91 => 0x201A,
            0x92 => 0x2122,
            0x93 => 0xFB01,
            0x94 => 0xFB02,
            0x95 => 0x0141,
            0x96 => 0x0152,
            0x97 => 0x0160,
            0x98 => 0x0178,
            0x99 => 0x017D,
            0x9A => 0x0131,
            0x9B => 0x0142,
            0x9C => 0x0153,
            0x9D => 0x0161,
            0x9E => 0x017E,
            0x9F => null,
            0xA0 => 0x20AC,
        ];
    }
}
