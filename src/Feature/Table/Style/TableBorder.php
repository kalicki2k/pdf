<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Style;

use InvalidArgumentException;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final readonly class TableBorder
{
    private function __construct(
        public ?float $width,
        public ?Color $color,
        public ?Opacity $opacity,
        public ?bool $top,
        public ?bool $right,
        public ?bool $bottom,
        public ?bool $left,
    ) {
        if ($this->width !== null && $this->width <= 0) {
            throw new InvalidArgumentException('Table border width must be greater than zero.');
        }
    }

    public static function all(float $width = 1.0, ?Color $color = null, ?Opacity $opacity = null): self
    {
        return new self($width, $color, $opacity, true, true, true, true);
    }

    public static function none(): null
    {
        return null;
    }

    public static function horizontal(float $width = 1.0, ?Color $color = null, ?Opacity $opacity = null): self
    {
        return new self($width, $color, $opacity, true, null, true, null);
    }

    public static function vertical(float $width = 1.0, ?Color $color = null, ?Opacity $opacity = null): self
    {
        return new self($width, $color, $opacity, null, true, null, true);
    }

    /**
     * @param list<'top'|'right'|'bottom'|'left'> $sides
     */
    public static function only(array $sides, float $width = 1.0, ?Color $color = null, ?Opacity $opacity = null): self
    {
        return new self(
            $width,
            $color,
            $opacity,
            in_array('top', $sides, true) ? true : null,
            in_array('right', $sides, true) ? true : null,
            in_array('bottom', $sides, true) ? true : null,
            in_array('left', $sides, true) ? true : null,
        );
    }

    public function isDefinedFor(string $side): bool
    {
        return match ($side) {
            'top' => $this->top !== null,
            'right' => $this->right !== null,
            'bottom' => $this->bottom !== null,
            'left' => $this->left !== null,
            default => throw new InvalidArgumentException("Unsupported border side '$side'."),
        };
    }

    public function hasAnySide(): bool
    {
        return $this->top === true || $this->right === true || $this->bottom === true || $this->left === true;
    }

    public function isAll(): bool
    {
        return $this->top === true && $this->right === true && $this->bottom === true && $this->left === true;
    }

    public function isEnabled(string $side): bool
    {
        return match ($side) {
            'top' => $this->top === true,
            'right' => $this->right === true,
            'bottom' => $this->bottom === true,
            'left' => $this->left === true,
            default => throw new InvalidArgumentException("Unsupported border side '$side'."),
        };
    }
}
