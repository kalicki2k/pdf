<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Text\TextSegment;

final readonly class CellRenderOptions
{
    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $remainingLines
     */
    public function __construct(
        public ?int $visibleRowspan = null,
        public bool $renderText = true,
        public bool $renderTopBorder = true,
        public bool $renderBottomBorder = true,
        public array $remainingLines = [],
    ) {
    }
}
