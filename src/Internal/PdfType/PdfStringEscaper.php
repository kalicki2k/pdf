<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\PdfType;

final class PdfStringEscaper
{
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', '(', ')', "\r", "\n", "\t", chr(8), "\f"],
            ['\\\\', '\(', '\)', '\r', '\n', '\t', '\b', '\f'],
            $value,
        );
    }
}
