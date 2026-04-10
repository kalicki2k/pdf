<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Render\PdfOutput;

final readonly class ArrayType implements Type
{
    use RendersPdfType;

    /**
     * @param list<Type|int|float> $values
     */
    public function __construct(private array $values)
    {
    }

    public function write(PdfOutput $output, ?ObjectStringEncryptor $encryptor = null): void
    {
        $output->write('[');

        foreach ($this->values as $index => $value) {
            if ($index > 0) {
                $output->write(' ');
            }

            self::writeEntryValue($output, $value, $encryptor);
        }

        $output->write(']');
    }

    private static function writeEntryValue(
        PdfOutput $output,
        Type | int | float $value,
        ?ObjectStringEncryptor $encryptor,
    ): void {
        if ($value instanceof Type) {
            $value->write($output, $encryptor);

            return;
        }

        $output->write((string) $value);
    }
}
