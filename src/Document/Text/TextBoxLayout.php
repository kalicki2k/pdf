<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

use InvalidArgumentException;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Layout\VerticalAlign;

/**
 * @internal Holds validated layout values for text rendered inside a fixed box.
 */
final readonly class TextBoxLayout
{
    private function __construct(
        public float $contentX,
        public float $contentWidth,
        public float $lineHeight,
        public int $maxLines,
        private float $boxY,
        private float $boxHeight,
        private float $paddingTop,
        private float $paddingBottom,
        private VerticalAlign $verticalAlign,
    ) {
    }

    public static function fromOptions(
        Rect $box,
        int $size,
        TextBoxOptions $options,
        float $defaultLineHeightFactor,
    ): self {
        $lineHeight = $options->lineHeight ?? $size * $defaultLineHeightFactor;

        if ($box->width <= 0) {
            throw new InvalidArgumentException('Text box width must be greater than zero.');
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException('Text box height must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        if ($options->maxLines !== null && $options->maxLines <= 0) {
            throw new InvalidArgumentException('Max lines must be greater than zero.');
        }

        if (
            $options->padding->top < 0
            || $options->padding->right < 0
            || $options->padding->bottom < 0
            || $options->padding->left < 0
        ) {
            throw new InvalidArgumentException('Text box padding must not be negative.');
        }

        $contentWidth = $box->width - $options->padding->left - $options->padding->right;

        if ($contentWidth <= 0) {
            throw new InvalidArgumentException('Text box content width must be greater than zero.');
        }

        $contentHeight = $box->height - $options->padding->top - $options->padding->bottom;

        if ($contentHeight < $size) {
            throw new InvalidArgumentException('Text box content height must be at least the font size.');
        }

        $visibleLineCapacity = max(1, (int) floor($contentHeight / $lineHeight));
        $maxLines = $options->maxLines === null
            ? $visibleLineCapacity
            : min($options->maxLines, $visibleLineCapacity);

        return new self(
            $box->x + $options->padding->left,
            $contentWidth,
            $lineHeight,
            $maxLines,
            $box->y,
            $box->height,
            $options->padding->top,
            $options->padding->bottom,
            $options->verticalAlign,
        );
    }

    public function resolveStartY(int $size, int $lineCount): float
    {
        $availableHeight = $this->boxHeight - $this->paddingTop - $this->paddingBottom;
        $lineOffset = max(0, $lineCount - 1) * $this->lineHeight;
        $blockHeight = $size + $lineOffset;

        return match ($this->verticalAlign) {
            VerticalAlign::TOP => $this->boxY + $this->paddingBottom + $availableHeight - $size,
            VerticalAlign::MIDDLE => $this->boxY + $this->paddingBottom + (($availableHeight - $blockHeight) / 2) + $lineOffset,
            VerticalAlign::BOTTOM => $this->boxY + $this->paddingBottom + $lineOffset,
        };
    }
}
