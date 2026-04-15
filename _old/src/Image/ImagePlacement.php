<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use InvalidArgumentException;
use Kalle\Pdf\Layout\PositionMode;

/**
 * Describes the placement and rendered size of an image block.
 */
final readonly class ImagePlacement
{
    /**
     * Creates an image placement relative to the full page box.
     *
     * @param ?float $left Left inset from the page edge.
     * @param ?float $right Right inset from the page edge.
     * @param ?float $top Top inset from the page edge.
     * @param ?float $bottom Bottom inset from the page edge.
     * @param ?float $width Explicit rendered image width.
     * @param ?float $height Explicit rendered image height.
     */
    public static function absolute(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $bottom = null,
        ?float $width = null,
        ?float $height = null,
    ): self {
        return new self(
            positionMode: PositionMode::ABSOLUTE,
            left: $left,
            right: $right,
            top: $top,
            bottom: $bottom,
            width: $width,
            height: $height,
        );
    }

    /**
     * Creates an image placement relative to the current page content area.
     *
     * @param ?float $left Left inset from the content area edge.
     * @param ?float $right Right inset from the content area edge.
     * @param ?float $top Top inset from the content area edge.
     * @param ?float $bottom Bottom inset from the content area edge.
     * @param ?float $width Explicit rendered image width.
     * @param ?float $height Explicit rendered image height.
     */
    public static function relative(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $bottom = null,
        ?float $width = null,
        ?float $height = null,
    ): self {
        return new self(
            positionMode: PositionMode::RELATIVE,
            left: $left,
            right: $right,
            top: $top,
            bottom: $bottom,
            width: $width,
            height: $height,
        );
    }

    /**
     * Creates a flow-based image placement at the current page cursor.
     *
     * @param ?float $width Explicit rendered image width.
     * @param ?float $height Explicit rendered image height.
     * @param ImageAlign $align Horizontal alignment within the current content area.
     * @param float $spacingBefore Additional spacing inserted before the image in flow layout.
     * @param float $spacingAfter Additional spacing inserted after the image in flow layout.
     */
    public static function static(
        ?float $width = null,
        ?float $height = null,
        ImageAlign $align = ImageAlign::LEFT,
        float $spacingBefore = 0.0,
        float $spacingAfter = 0.0,
    ): self {
        return new self(
            width: $width,
            height: $height,
            align: $align,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
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

    public function isStatic(): bool
    {
        return $this->positionMode === PositionMode::STATIC;
    }

    private function __construct(
        public PositionMode $positionMode = PositionMode::STATIC,
        public ?float $left = null,
        public ?float $right = null,
        public ?float $top = null,
        public ?float $bottom = null,
        public ?float $width = null,
        public ?float $height = null,
        public ?ImageAlign $align = null,
        public float $spacingBefore = 0.0,
        public float $spacingAfter = 0.0,
    ) {
        if ($this->width !== null && $this->width <= 0.0) {
            throw new InvalidArgumentException('Image width must be greater than 0.');
        }

        if ($this->height !== null && $this->height <= 0.0) {
            throw new InvalidArgumentException('Image height must be greater than 0.');
        }

        if ($this->spacingBefore < 0.0) {
            throw new InvalidArgumentException('Image spacingBefore must be greater than or equal to 0.');
        }

        if ($this->spacingAfter < 0.0) {
            throw new InvalidArgumentException('Image spacingAfter must be greater than or equal to 0.');
        }

        if ($this->positionMode === PositionMode::STATIC) {
            if ($this->left !== null || $this->right !== null || $this->top !== null || $this->bottom !== null) {
                throw new InvalidArgumentException('Static image placement does not support explicit left/right/top/bottom insets.');
            }

            return;
        }

        if ($this->align !== null) {
            throw new InvalidArgumentException('Absolute and relative image placement do not support flow alignment.');
        }

        if ($this->spacingBefore !== 0.0 || $this->spacingAfter !== 0.0) {
            throw new InvalidArgumentException('Absolute and relative image placement do not support flow spacing.');
        }

        if ($this->left !== null && $this->right !== null) {
            throw new InvalidArgumentException('Image placement cannot combine left and right insets.');
        }

        if ($this->top !== null && $this->bottom !== null) {
            throw new InvalidArgumentException('Image placement cannot combine top and bottom insets.');
        }

        if ($this->left === null && $this->right === null) {
            throw new InvalidArgumentException('Image placement requires either left or right.');
        }

        if ($this->top === null && $this->bottom === null) {
            throw new InvalidArgumentException('Image placement requires either top or bottom.');
        }
    }
}
