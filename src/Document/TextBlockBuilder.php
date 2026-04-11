<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Text\TextOptions;

use function implode;
use function number_format;
use function str_replace;
use function strlen;

final readonly class TextBlockBuilder
{
    /**
     * @param list<?string> $glyphNames
     */
    public function build(
        string $encodedText,
        TextOptions $options,
        float $x,
        float $y,
        string $fontAlias,
        StandardFontDefinition $font,
        array $glyphNames = [],
        bool $useHexString = false,
    ): string {
        $lines = [
            'BT',
        ];

        if ($options->color !== null) {
            $lines[] = $this->buildFillColorOperator($options->color);
        }

        $lines = [
            ...$lines,
            '/' . $fontAlias . ' ' . $this->formatNumber($options->fontSize) . ' Tf',
            $this->formatNumber($x) . ' ' . $this->formatNumber($y) . ' Td',
            $this->buildTextShowOperator($encodedText, $font, $glyphNames, $useHexString),
            'ET',
        ];

        return implode("\n", $lines);
    }

    private function buildFillColorOperator(Color $color): string
    {
        $components = array_map(
            fn (float $value): string => $this->formatNumber($value),
            $color->components(),
        );

        return match ($color->space) {
            ColorSpace::GRAY => implode(' ', $components) . ' g',
            ColorSpace::RGB => implode(' ', $components) . ' rg',
            ColorSpace::CMYK => implode(' ', $components) . ' k',
        };
    }

    /**
     * @param list<?string> $glyphNames
     */
    private function buildTextShowOperator(
        string $encodedText,
        StandardFontDefinition $font,
        array $glyphNames,
        bool $useHexString,
    ): string {
        $kerningOperator = $this->buildKerningTextOperator($encodedText, $font, $glyphNames);

        if ($kerningOperator !== null) {
            return $kerningOperator;
        }

        return ($useHexString ? $this->pdfHexString($encodedText) : $this->pdfLiteralString($encodedText)) . ' Tj';
    }

    /**
     * @param list<?string> $glyphNames
     */
    private function buildKerningTextOperator(
        string $encodedText,
        StandardFontDefinition $font,
        array $glyphNames,
    ): ?string {
        if ($glyphNames === [] || strlen($encodedText) < 2) {
            return null;
        }

        $bytes = str_split($encodedText);

        if (count($bytes) !== count($glyphNames)) {
            return null;
        }

        $parts = [];
        $hasKerning = false;

        foreach ($bytes as $index => $byte) {
            $parts[] = $this->pdfHexString($byte);

            if (!isset($bytes[$index + 1])) {
                continue;
            }

            $leftGlyph = $glyphNames[$index];
            $rightGlyph = $glyphNames[$index + 1];

            if ($leftGlyph === null || $rightGlyph === null) {
                continue;
            }

            $kerning = $font->kerningValue($leftGlyph, $rightGlyph);

            if ($kerning === 0) {
                continue;
            }

            $parts[] = (string) -$kerning;
            $hasKerning = true;
        }

        if (!$hasKerning) {
            return null;
        }

        return '[' . implode(' ', $parts) . '] TJ';
    }

    private function pdfLiteralString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }

    private function pdfHexString(string $value): string
    {
        return '<' . bin2hex($value) . '>';
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
