<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Rendering;

use Kalle\Pdf\Feature\Text\TextSegment;
use Kalle\Pdf\Internal\Page\Page;

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
