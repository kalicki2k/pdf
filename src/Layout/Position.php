<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

use InvalidArgumentException;

final readonly class Position
{
    private function __construct(
        private PositionMode $mode,
        public ?float        $top = null,
        public ?float        $left = null,
        public ?float        $right = null,
        public ?float        $bottom = null,
    ) {
        $hasInset = $this->top !== null
            || $this->left !== null
            || $this->right !== null
            || $this->bottom !== null;

        if (!$hasInset && $this->mode !== PositionMode::STATIC) {
            throw new InvalidArgumentException('Absolute and relative positions must define at least one inset.');
        }

        if ($hasInset && $this->mode === PositionMode::STATIC) {
            throw new InvalidArgumentException('Static positions cannot define insets.');
        }

        foreach ([$this->top, $this->left, $this->right, $this->bottom] as $inset) {
            if ($inset !== null && $inset < 0.0) {
                throw new InvalidArgumentException('Position insets must be greater than or equal to 0.');
            }
        }
    }

    public static function absolute(
        ?float $top = null,
        ?float $left = null,
        ?float $right = null,
        ?float $bottom = null,
    ): self {
        return new self(
            mode: PositionMode::ABSOLUTE,
            top: $top,
            left: $left,
            right: $right,
            bottom: $bottom,
        );
    }

    public static function relative(
        ?float $top = null,
        ?float $left = null,
        ?float $right = null,
        ?float $bottom = null,
    ): self {
        return new self(
            mode: PositionMode::RELATIVE,
            top: $top,
            left: $left,
            right: $right,
            bottom: $bottom,
        );
    }

    public static function static(): self
    {
        return new self(
            mode: PositionMode::STATIC,
        );
    }

    public function mode(): PositionMode
    {
        return $this->mode;
    }

    public function isAbsolute(): bool
    {
        return $this->mode === PositionMode::ABSOLUTE;
    }

    public function isRelative(): bool
    {
        return $this->mode === PositionMode::RELATIVE;
    }

    public function isStatic(): bool
    {
        return $this->mode === PositionMode::STATIC;
    }
}
