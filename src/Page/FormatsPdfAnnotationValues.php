<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function array_map;
use function implode;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;

use function number_format;
use function rtrim;
use function str_pad;
use function str_replace;
use function str_split;
use function strtoupper;

trait FormatsPdfAnnotationValues
{
    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }

    private function pdfName(string $value): string
    {
        $encoded = '';

        foreach (str_split($value) as $character) {
            $ord = ord($character);

            if (
                ($ord >= 48 && $ord <= 57)
                || ($ord >= 65 && $ord <= 90)
                || ($ord >= 97 && $ord <= 122)
                || $character === '-'
                || $character === '_'
                || $character === '.'
            ) {
                $encoded .= $character;

                continue;
            }

            $encoded .= '#' . strtoupper(str_pad(dechex($ord), 2, '0', STR_PAD_LEFT));
        }

        return $encoded;
    }

    /**
     * @return list<float>
     */
    private function colorComponents(Color $color): array
    {
        return $color->components();
    }

    private function pdfColorArray(Color $color): string
    {
        return '[' . implode(' ', array_map($this->formatNumber(...), $this->colorComponents($color))) . ']';
    }

    private function nonStrokingColorOperator(Color $color): string
    {
        $components = implode(' ', array_map($this->formatNumber(...), $this->colorComponents($color)));

        return match ($color->space) {
            ColorSpace::GRAY => $components . ' g',
            ColorSpace::RGB => $components . ' rg',
            ColorSpace::CMYK => $components . ' k',
        };
    }

    private function strokingColorOperator(Color $color): string
    {
        $components = implode(' ', array_map($this->formatNumber(...), $this->colorComponents($color)));

        return match ($color->space) {
            ColorSpace::GRAY => $components . ' G',
            ColorSpace::RGB => $components . ' RG',
            ColorSpace::CMYK => $components . ' K',
        };
    }

    private function rect(float $x, float $y, float $width, float $height): string
    {
        return '[' . $this->formatNumber($x) . ' '
            . $this->formatNumber($y) . ' '
            . $this->formatNumber($x + $width) . ' '
            . $this->formatNumber($y + $height) . ']';
    }

    private function quadPoints(float $x, float $y, float $width, float $height): string
    {
        return '['
            . $this->formatNumber($x) . ' '
            . $this->formatNumber($y + $height) . ' '
            . $this->formatNumber($x + $width) . ' '
            . $this->formatNumber($y + $height) . ' '
            . $this->formatNumber($x) . ' '
            . $this->formatNumber($y) . ' '
            . $this->formatNumber($x + $width) . ' '
            . $this->formatNumber($y)
            . ']';
    }

    private function borderStyleDictionary(AnnotationBorderStyle $style): string
    {
        return $style->pdfDictionaryContents();
    }
}
