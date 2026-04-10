<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;

final readonly class ArrayType implements Type
{
    /**
     * @param list<Type|int|float> $values
     */
    public function __construct(private array $values)
    {
    }

    public function render(?ObjectStringEncryptor $encryptor = null): string
    {
        $items = array_map(
            static fn (Type | int | float $value): string => self::renderValue($value, $encryptor),
            $this->values,
        );

        return '[' . implode(' ', $items) . ']';
    }

    private static function renderValue(
        Type | int | float $value,
        ?ObjectStringEncryptor $encryptor,
    ): string {
        return $value instanceof Type ? $value->render($encryptor) : (string) $value;
    }
}
