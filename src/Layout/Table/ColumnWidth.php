<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use InvalidArgumentException;

final readonly class ColumnWidth
{
    private const TYPE_FIXED = 'fixed';
    private const TYPE_PROPORTIONAL = 'proportional';

    private function __construct(
        private string $type,
        public float $value,
    ) {
        if ($this->value <= 0.0) {
            throw new InvalidArgumentException('Column width values must be greater than zero.');
        }
    }

    public static function fixed(float $width): self
    {
        return new self(self::TYPE_FIXED, $width);
    }

    public static function proportional(float $weight): self
    {
        return new self(self::TYPE_PROPORTIONAL, $weight);
    }

    public function isFixed(): bool
    {
        return $this->type === self::TYPE_FIXED;
    }

    public function isProportional(): bool
    {
        return $this->type === self::TYPE_PROPORTIONAL;
    }
}
