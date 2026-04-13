<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use InvalidArgumentException;

final readonly class ColumnWidth
{
    private const string TYPE_FIXED = 'fixed';
    private const string TYPE_AUTO = 'auto';
    private const string TYPE_PROPORTIONAL = 'proportional';

    private function __construct(
        private string $type,
        public float $value,
    ) {
        if ($this->type === self::TYPE_AUTO) {
            return;
        }

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

    public static function auto(): self
    {
        return new self(self::TYPE_AUTO, 0.0);
    }

    public function isFixed(): bool
    {
        return $this->type === self::TYPE_FIXED;
    }

    public function isProportional(): bool
    {
        return $this->type === self::TYPE_PROPORTIONAL;
    }

    public function isAuto(): bool
    {
        return $this->type === self::TYPE_AUTO;
    }
}
