<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table\Rendering;

use Kalle\Pdf\Page;

final readonly class TableCaptionRenderResult
{
    public function __construct(
        public Page $page,
        public float $cursorY,
    ) {
    }
}
