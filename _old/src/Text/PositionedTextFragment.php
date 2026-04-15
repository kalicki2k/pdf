<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class PositionedTextFragment
{
    public function __construct(
        public string $encodedText,
        public float $xOffset,
        public float $yOffset,
    ) {
    }
}
