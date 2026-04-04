<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

final readonly class ArrayType implements Type
{
    /**
     * @param list<Type|int|float> $values
     */
    public function __construct(private array $values)
    {
    }

    public function render(): string
    {
        $items = array_map(
            static fn (Type | int | float $value): string => self::renderValue($value),
            $this->values,
        );

        return '[' . implode(' ', $items) . ']';
    }

    private static function renderValue(Type | int | float $value): string
    {
        return $value instanceof Type ? $value->render() : (string) $value;
    }
}
