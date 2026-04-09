<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Feature\Text\TextSegment;

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
