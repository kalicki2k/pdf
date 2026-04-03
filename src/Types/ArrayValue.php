<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

final readonly class ArrayValue implements Value
{
    /**
     * @param list<Value|int|float> $values
     */
    public function __construct(private array $values)
    {
    }

    public function render(): string
    {
        $items = array_map(
            static fn (Value | int | float $value): string => self::renderValue($value),
            $this->values,
        );

        return '[' . implode(' ', $items) . ']';
    }

    private static function renderValue(Value | int | float $value): string
    {
        return $value instanceof Value ? $value->render() : (string) $value;
    }
}
