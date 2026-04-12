<?php

declare(strict_types=1);

namespace Kalle\Pdf\Drawing;

use InvalidArgumentException;

final readonly class Path
{
    /**
     * @param list<array{operator: string, values: list<float>}> $commands
     */
    public function __construct(
        private array $commands,
    ) {
        if ($this->commands === []) {
            throw new InvalidArgumentException('Path requires at least one drawing command.');
        }
    }

    public static function builder(): PathBuilder
    {
        return new PathBuilder();
    }

    public static function roundedRectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius,
    ): self {
        if ($width <= 0.0) {
            throw new InvalidArgumentException('Rounded rectangle width must be greater than zero.');
        }

        if ($height <= 0.0) {
            throw new InvalidArgumentException('Rounded rectangle height must be greater than zero.');
        }

        if ($radius <= 0.0) {
            throw new InvalidArgumentException('Rounded rectangle radius must be greater than zero.');
        }

        if ($radius > ($width / 2) || $radius > ($height / 2)) {
            throw new InvalidArgumentException('Rounded rectangle radius must not exceed half the width or height.');
        }

        $controlOffset = $radius * 0.5522847498307936;
        $left = $x;
        $right = $x + $width;
        $bottom = $y;
        $top = $y + $height;

        return self::builder()
            ->moveTo($left + $radius, $top)
            ->lineTo($right - $radius, $top)
            ->curveTo(
                $right - $radius + $controlOffset,
                $top,
                $right,
                $top - $radius + $controlOffset,
                $right,
                $top - $radius,
            )
            ->lineTo($right, $bottom + $radius)
            ->curveTo(
                $right,
                $bottom + $radius - $controlOffset,
                $right - $radius + $controlOffset,
                $bottom,
                $right - $radius,
                $bottom,
            )
            ->lineTo($left + $radius, $bottom)
            ->curveTo(
                $left + $radius - $controlOffset,
                $bottom,
                $left,
                $bottom + $radius - $controlOffset,
                $left,
                $bottom + $radius,
            )
            ->lineTo($left, $top - $radius)
            ->curveTo(
                $left,
                $top - $radius + $controlOffset,
                $left + $radius - $controlOffset,
                $top,
                $left + $radius,
                $top,
            )
            ->close()
            ->build();
    }

    /**
     * @return list<array{operator: string, values: list<float>}>
     */
    public function commands(): array
    {
        return $this->commands;
    }
}
