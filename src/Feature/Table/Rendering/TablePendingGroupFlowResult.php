<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Rendering;

use Kalle\Pdf\Internal\Page\Page;

/**
 * @internal Carries the updated page state after rendering a pending table row group flow.
 */
final readonly class TablePendingGroupFlowResult
{
    public function __construct(
        public Page $page,
        public float $cursorY,
    ) {
    }
}
