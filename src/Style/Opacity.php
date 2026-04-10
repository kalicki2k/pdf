<?php

declare(strict_types=1);

namespace Kalle\Pdf\Style;

use InvalidArgumentException;

final readonly class Opacity
{
    private function __construct(
        private ?float $fill,
        private ?float $stroke,
    ) {
    }

    public static function fill(float $value): self
    {
        self::assertUnitInterval($value, 'fill opacity');

        return new self($value, null);
    }

    public static function stroke(float $value): self
    {
        self::assertUnitInterval($value, 'stroke opacity');

        return new self(null, $value);
    }

    public static function both(float $value): self
    {
        self::assertUnitInterval($value, 'opacity');

        return new self($value, $value);
    }

    public function renderExtGStateDictionary(): string
    {
        $entries = [];

        if ($this->fill !== null) {
            $entries[] = '/ca ' . self::formatValue($this->fill);
        }

        if ($this->stroke !== null) {
            $entries[] = '/CA ' . self::formatValue($this->stroke);
        }

        if ($entries === []) {
            return '<< >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private static function assertUnitInterval(float $value, string $name): void
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException(ucfirst($name) . " must be between 0.0 and 1.0, got $value.");
        }
    }

    private static function formatValue(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
