<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

final class Dictionary implements Value
{
    /**
     * @param array<string, Value|string|int|float> $entries
     */
    public function __construct(private array $entries)
    {
    }

    public function add(string $name, Value | string | int | float $entry): self
    {
        $this->entries[$name] = $entry;

        return $this;
    }

    public function render(): string
    {
        $parts = [];

        foreach ($this->entries as $key => $value) {
            $parts[] = '/' . $key . ' ' . self::renderValue($value);
        }

        return '<< ' . implode(' ', $parts) . ' >>';
    }

    private static function renderValue(Value | string | int | float $value): string
    {
        return $value instanceof Value ? $value->render() : (string) $value;
    }
}
