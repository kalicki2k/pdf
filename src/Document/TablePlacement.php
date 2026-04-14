<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Layout\PositionMode;

/**
 * Describes the horizontal anchor and optional top start position of a table.
 */
final readonly class TablePlacement
{
    /**
     * Creates a table placement relative to the full page box.
     *
     * @param ?float $left Left inset from the page edge.
     * @param ?float $right Right inset from the page edge.
     * @param ?float $top Top inset from the page edge. `0` starts at the top edge.
     * @param ?float $width Explicit table width.
     */
    public static function absolute(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $width = null,
    ): self {
        return new self(
            positionMode: PositionMode::ABSOLUTE,
            left: $left,
            right: $right,
            top: $top,
            width: $width,
        );
    }

    /**
     * Creates a table placement relative to the page content area.
     *
     * @param ?float $left Left inset from the content area edge.
     * @param ?float $right Right inset from the content area edge.
     * @param ?float $top Top inset from the content area edge. `0` starts at the top edge.
     * @param ?float $width Explicit table width.
     */
    public static function relative(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $width = null,
    ): self {
        return new self(
            positionMode: PositionMode::RELATIVE,
            left: $left,
            right: $right,
            top: $top,
            width: $width,
        );
    }

    public function isAbsolute(): bool
    {
        return $this->positionMode === PositionMode::ABSOLUTE;
    }

    public function isRelative(): bool
    {
        return $this->positionMode === PositionMode::RELATIVE;
    }

    private function __construct(
        public PositionMode $positionMode = PositionMode::RELATIVE,
        public ?float $left = null,
        public ?float $right = null,
        public ?float $top = null,
        public ?float $width = null,
    ) {
        if ($this->width !== null && $this->width <= 0.0) {
            throw new InvalidArgumentException('Table placement width must be greater than zero.');
        }

        if ($this->left === null && $this->right === null) {
            throw new InvalidArgumentException('Table placement requires either left or right.');
        }

        if ($this->width === null && ($this->left === null || $this->right === null)) {
            throw new InvalidArgumentException('Table placement requires a width unless both left and right are provided.');
        }
    }
}
