<?php

declare(strict_types=1);

namespace Kalle\Pdf\Utilities;

final class StringListNormalizer
{
    /**
     * @param string[] $values
     * @return string[]
     */
    public static function unique(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (string $value): string => trim($value),
                $values,
            ),
            static fn (string $value): bool => $value !== '',
        )));
    }
}
