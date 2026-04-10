<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Text;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Text\Input\FlowTextOptions;

/**
 * @internal Holds validated layout values for flow text rendering.
 */
final readonly class FlowTextLayout
{
    private function __construct(
        public float $lineHeight,
        public float $bottomMargin,
        public ?int $maxLines,
    ) {
    }

    public static function fromOptions(
        float $maxWidth,
        int $size,
        FlowTextOptions $options,
        float $defaultLineHeightFactor,
        float $defaultBottomMargin,
    ): self {
        $lineHeight = $options->lineHeight ?? $size * $defaultLineHeightFactor;
        $bottomMargin = $options->bottomMargin ?? $defaultBottomMargin;

        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        if ($options->maxLines !== null && $options->maxLines <= 0) {
            throw new InvalidArgumentException('Max lines must be greater than zero.');
        }

        return new self($lineHeight, $bottomMargin, $options->maxLines);
    }
}
