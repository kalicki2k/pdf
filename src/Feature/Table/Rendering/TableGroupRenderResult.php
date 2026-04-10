<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Rendering;

use Kalle\Pdf\Internal\Page\Page;

/**
 * @internal Carries the updated page state after rendering a prepared table row group.
 */
final class TableGroupRenderResult
{
    public function __construct(
        public readonly Page $page,
        public readonly float $cursorY,
    ) {
    }
}
