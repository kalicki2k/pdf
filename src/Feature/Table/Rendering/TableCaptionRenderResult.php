<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Rendering;

use Kalle\Pdf\Document\Page;

final readonly class TableCaptionRenderResult
{
    public function __construct(
        public Page $page,
        public float $cursorY,
    ) {
    }
}
