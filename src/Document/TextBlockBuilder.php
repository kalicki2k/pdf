<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function implode;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

use Kalle\Pdf\Text\PositionedTextFragment;
use Kalle\Pdf\Text\TextOptions;

use function number_format;
use function str_replace;
use function strlen;

final readonly class TextBlockBuilder
{
    public function __construct(
        private ?Debugger $debugger = null,
    ) {
    }

    /**
     * @param list<?string> $glyphNames
     * @param list<int> $textAdjustments
     * @param list<PositionedTextFragment> $positionedFragments
     */
    public function build(
        string $encodedText,
        TextOptions $options,
        float $x,
        float $y,
        string $fontAlias,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        array $glyphNames = [],
        array $textAdjustments = [],
        array $positionedFragments = [],
        bool $useHexString = false,
    ): string {
        $scope = ($this->debugger ?? Debugger::disabled())->startPerformanceScope('text.content.block', [
            'glyph_name_count' => count($glyphNames),
            'adjustment_count' => count($textAdjustments),
            'fragment_count' => count($positionedFragments),
            'use_hex_string' => $useHexString ? 1 : 0,
        ]);
        $lines = [
            'BT',
        ];

        if ($options->color !== null) {
            $lines[] = $this->buildFillColorOperator($options->color);
        }

        if ($positionedFragments !== []) {
            $lines[] = '/' . $fontAlias . ' ' . $this->formatNumber($options->fontSize) . ' Tf';
            $lines = [
                ...$lines,
                ...$this->buildPositionedFragmentOperators($positionedFragments, $x, $y, $useHexString),
                'ET',
            ];

            $result = implode("\n", $lines);
            $scope->stop([
                'content_length' => strlen($result),
                'mode' => 'fragments',
            ]);

            return $result;
        }

        $lines = [
            ...$lines,
            '/' . $fontAlias . ' ' . $this->formatNumber($options->fontSize) . ' Tf',
            $this->formatNumber($x) . ' ' . $this->formatNumber($y) . ' Td',
            $this->buildTextShowOperator($encodedText, $font, $glyphNames, $textAdjustments, $useHexString),
            'ET',
        ];

        $result = implode("\n", $lines);
        $scope->stop([
            'content_length' => strlen($result),
            'mode' => 'inline',
        ]);

        return $result;
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
     * @param list<int> $textAdjustments
     */
    private function buildTextShowOperator(
        string $encodedText,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        array $glyphNames,
        array $textAdjustments,
        bool $useHexString,
    ): string {
        $adjustedOperator = $this->buildAdjustedTextOperator(
            $encodedText,
            $font,
            $glyphNames,
            $textAdjustments,
            $useHexString,
        );

        if ($adjustedOperator !== null) {
            return $adjustedOperator;
        }

        return ($useHexString ? $this->pdfHexString($encodedText) : $this->pdfLiteralString($encodedText)) . ' Tj';
    }

    /**
     * @param list<?string> $glyphNames
     * @param list<int> $textAdjustments
     */
    private function buildAdjustedTextOperator(
        string $encodedText,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        array $glyphNames,
        array $textAdjustments,
        bool $useHexString,
    ): ?string {
        if ($textAdjustments === []) {
            return $this->buildKerningOperator($encodedText, $font, $glyphNames);
        }

        $glyphCount = max(count($glyphNames), count($textAdjustments) + 1);

        if (strlen($encodedText) < 2) {
            return null;
        }

        if (strlen($encodedText) % $glyphCount !== 0) {
            return null;
        }

        $adjustments = array_fill(0, $glyphCount - 1, 0);

        foreach ($this->buildKerningAdjustments($glyphNames, $font) as $index => $adjustment) {
            $adjustments[$index] = ($adjustments[$index] ?? 0) + $adjustment;
        }

        foreach ($textAdjustments as $index => $adjustment) {
            $adjustments[$index] = ($adjustments[$index] ?? 0) + $adjustment;
        }

        if (count(array_filter($adjustments, static fn (int $value): bool => $value !== 0)) === 0) {
            return null;
        }

        return $this->buildPositionedTextOperator($encodedText, array_values($adjustments), $useHexString);
    }

    /**
     * @param list<?string> $glyphNames
     */
    private function buildKerningOperator(
        string $encodedText,
        StandardFontDefinition | EmbeddedFontDefinition $font,
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

    /**
     * @param list<?string> $glyphNames
     * @return list<int>
     */
    private function buildKerningAdjustments(
        array $glyphNames,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): array {
        if ($glyphNames === [] || count($glyphNames) < 2) {
            return [];
        }

        $adjustments = [];

        foreach ($glyphNames as $index => $leftGlyph) {
            if (!isset($glyphNames[$index + 1])) {
                continue;
            }

            $rightGlyph = $glyphNames[$index + 1];

            if ($leftGlyph === null) {
                $adjustments[] = 0;

                continue;
            }

            $adjustments[] = -$font->kerningValue($leftGlyph, $rightGlyph);
        }

        return $adjustments;
    }

    /**
     * @param list<PositionedTextFragment> $positionedFragments
     * @return list<string>
     */
    private function buildPositionedFragmentOperators(
        array $positionedFragments,
        float $x,
        float $y,
        bool $useHexString,
    ): array {
        $operators = [];

        foreach ($positionedFragments as $fragment) {
            $operators[] = '1 0 0 1 '
                . $this->formatNumber($x + $fragment->xOffset)
                . ' '
                . $this->formatNumber($y + $fragment->yOffset)
                . ' Tm';
            $operators[] = ($useHexString
                ? $this->pdfHexString($fragment->encodedText)
                : $this->pdfLiteralString($fragment->encodedText))
                . ' Tj';
        }

        return $operators;
    }

    /**
     * @param list<int> $textAdjustments
     */
    private function buildPositionedTextOperator(string $encodedText, array $textAdjustments, bool $useHexString): ?string
    {
        $glyphCount = count($textAdjustments) + 1;

        if ($glyphCount < 2 || $encodedText === '') {
            return null;
        }

        $encodedLength = strlen($encodedText);

        if ($encodedLength % $glyphCount !== 0) {
            return null;
        }

        $unitLength = intdiv($encodedLength, $glyphCount);

        if ($unitLength <= 0) {
            return null;
        }

        $parts = [];

        for ($index = 0; $index < $glyphCount; $index++) {
            $chunk = substr($encodedText, $index * $unitLength, $unitLength);
            $parts[] = $useHexString ? $this->pdfHexString($chunk) : $this->pdfLiteralString($chunk);

            if (isset($textAdjustments[$index])) {
                $parts[] = (string) $textAdjustments[$index];
            }
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
