<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Rendering;

use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Page;

final readonly class CellRenderResult
{
    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $remainingLines
     */
    public function __construct(
        public Page $page,
        public array $remainingLines = [],
    ) {
    }
}
