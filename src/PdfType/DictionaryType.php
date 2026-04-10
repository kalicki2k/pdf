<?php

declare(strict_types=1);

namespace Kalle\Pdf\PdfType;

use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;

final class DictionaryType implements Type
{
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

    public function render(?ObjectStringEncryptor $encryptor = null): string
    {
        $parts = [];

        foreach ($this->entries as $key => $value) {
            $parts[] = '/' . $key . ' ' . self::renderValue($value, $encryptor);
        }

        return '<< ' . implode(' ', $parts) . ' >>';
    }

    private static function renderValue(
        Type | string | int | float $value,
        ?ObjectStringEncryptor $encryptor,
    ): string {
        return $value instanceof Type ? $value->render($encryptor) : (string) $value;
    }
}
