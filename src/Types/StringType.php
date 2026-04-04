<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

use Kalle\Pdf\Utilities\PdfStringEscaper;

final class StringType implements Type
{
    public function __construct(private readonly string $value)
    {
    }

    public function render(): string
    {
        if ($this->canBeEncodedAsWindows1252($this->value)) {
            $encoded = mb_convert_encoding($this->value, 'Windows-1252', 'UTF-8');

            return '(' . PdfStringEscaper::escape($encoded) . ')';
        }

        $utf16be = mb_convert_encoding($this->value, 'UTF-16BE', 'UTF-8');

        return '<FEFF' . strtoupper(bin2hex($utf16be)) . '>';
    }

    private function canBeEncodedAsWindows1252(string $value): bool
    {
        $encoded = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        return $roundTrip === $value;
    }
}
