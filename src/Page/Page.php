<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Color\Color;

/**
 * Immutable page model for the document structure.
 */
final readonly class Page
{
    /**
     * @param array<string, PageFont> $fontResources
     */
    public function __construct(
        public PageSize $size,
        public string $contents = '',
        public array $fontResources = [],
        public ?Margin $margin = null,
        public ?Color $backgroundColor = null,
        public ?string $label = null,
        public ?string $name = null,
    ) {
    }
}
