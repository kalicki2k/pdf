<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Layout\FlowCursor;

final readonly class TextLayoutResult
{
    /**
     * @param list<string> $lines
     */
    public static function make(
        float $x,
        float $y,
        array $lines,
        FlowCursor $nextCursor,
    ): self {
        return new self(
            x: $x,
            y: $y,
            lines: $lines,
            nextCursor: $nextCursor,
        );
    }

    /**
     * @param list<string> $lines
     */
    private function __construct(
        public float $x,
        public float $y,
        public array $lines,
        public FlowCursor $nextCursor,
    ) {
    }
}