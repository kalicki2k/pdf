<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Rendering;

use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Text\TextSegment;

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
