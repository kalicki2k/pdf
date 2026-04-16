<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

/**
 * Serializes positioned text values into a minimal PDF text content block.
 */
final readonly class TextWriter
{
    /**
     * Renders a single text instruction at the given page coordinates.
     */
    public function write(string $text, float $x, float $y): string
    {
        $text = $this->escape($text);

        return "BT\n/F1 12 Tf\n$x $y Td\n($text) Tj\nET";
    }

    /**
     * Escapes characters that must be quoted inside PDF literal strings.
     */
    private function escape(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $text,
        );
    }
}
