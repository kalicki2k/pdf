<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Render\PdfOutput;

final class StringType implements Type
{
    use RendersPdfType;

    public function __construct(
        private readonly string $value,
        private readonly ?ObjectStringEncryptor $encryptor = null,
    ) {
    }

    public function write(PdfOutput $output, ?ObjectStringEncryptor $encryptor = null): void
    {
        $encryptor ??= $this->encryptor;

        if ($this->canBeEncodedAsWindows1252($this->value)) {
            $encoded = mb_convert_encoding($this->value, 'Windows-1252', 'UTF-8');
            $encrypted = $encryptor?->encrypt($encoded);

            if ($encrypted !== null) {
                $output->write('<' . strtoupper(bin2hex($encrypted)) . '>');

                return;
            }

            $output->write('(' . PdfStringEscaper::escape($encoded) . ')');

            return;
        }

        $utf16be = mb_convert_encoding($this->value, 'UTF-16BE', 'UTF-8');
        $encrypted = $encryptor?->encrypt("\xFE\xFF" . $utf16be);

        if ($encrypted !== null) {
            $output->write('<' . strtoupper(bin2hex($encrypted)) . '>');

            return;
        }

        $output->write('<FEFF' . strtoupper(bin2hex($utf16be)) . '>');
    }

    private function canBeEncodedAsWindows1252(string $value): bool
    {
        $encoded = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        return $roundTrip === $value;
    }
}
