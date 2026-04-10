<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Render\PdfOutput;

final class DictionaryType implements Type
{
    use RendersPdfType;

    /**
     * @param array<string, Type|string|int|float> $entries
     */
    public function __construct(private array $entries)
    {
    }

    public function add(string $name, Type | string | int | float $entry): self
    {
        $this->entries[$name] = $entry;

        return $this;
    }

    public function write(PdfOutput $output, ?ObjectStringEncryptor $encryptor = null): void
    {
        $output->write('<< ');

        $index = 0;

        foreach ($this->entries as $key => $value) {
            if ($index > 0) {
                $output->write(' ');
            }

            $output->write('/' . $key . ' ');
            self::writeEntryValue($output, $value, $encryptor);
            $index++;
        }

        $output->write(' >>');
    }

    private static function writeEntryValue(
        PdfOutput $output,
        Type | string | int | float $value,
        ?ObjectStringEncryptor $encryptor,
    ): void {
        if ($value instanceof Type) {
            $value->write($output, $encryptor);

            return;
        }

        $output->write((string) $value);
    }
}
