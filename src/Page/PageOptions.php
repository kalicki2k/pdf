<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Color\Color;

final readonly class PageOptions
{
    public function __construct(
        public ?PageSize $pageSize = null,
        public ?PageOrientation $orientation = null,
        public ?Margin $margin = null,
        public ?Color $backgroundColor = null,
        public ?string $label = null,
        public ?string $name = null,
    ) {
    }
}
