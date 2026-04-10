<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;

final class StringType implements Type
{
    public function __construct(
        private readonly string $value,
        private readonly ?ObjectStringEncryptor $encryptor = null,
    ) {
    }

    public function render(?ObjectStringEncryptor $encryptor = null): string
    {
        $encryptor ??= $this->encryptor;

        if ($this->canBeEncodedAsWindows1252($this->value)) {
            $encoded = mb_convert_encoding($this->value, 'Windows-1252', 'UTF-8');
            $encrypted = $encryptor?->encrypt($encoded);

            if ($encrypted !== null) {
                return '<' . strtoupper(bin2hex($encrypted)) . '>';
            }

            return '(' . PdfStringEscaper::escape($encoded) . ')';
        }

        $utf16be = mb_convert_encoding($this->value, 'UTF-16BE', 'UTF-8');
        $encrypted = $encryptor?->encrypt("\xFE\xFF" . $utf16be);

        if ($encrypted !== null) {
            return '<' . strtoupper(bin2hex($encrypted)) . '>';
        }

        return '<FEFF' . strtoupper(bin2hex($utf16be)) . '>';
    }

    private function canBeEncodedAsWindows1252(string $value): bool
    {
        $encoded = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        return $roundTrip === $value;
    }
}
