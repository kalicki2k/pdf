<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

/**
 * Measures text metrics in PDF points for a configured font.
 */
interface TextMeasurer
{
    public function width(string $text, float $fontSize = 12.0): float;

    public function ascent(float $fontSize = 12.0): float;

    public function descent(float $fontSize = 12.0): float;
}
