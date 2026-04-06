<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

use InvalidArgumentException;

final readonly class TableOfContentsOptions
{
    public string $title;
    public string $baseFont;
    public int $titleSize;
    public int $entrySize;
    public float $margin;
    public TableOfContentsPlacement $placement;
    public bool $useLogicalPageNumbers;
    public TableOfContentsStyle $style;

    public function __construct(
        string $title = 'Contents',
        string $baseFont = 'Helvetica',
        int $titleSize = 18,
        int $entrySize = 12,
        float $margin = 20.0,
        ?TableOfContentsPlacement $placement = null,
        bool $useLogicalPageNumbers = false,
        ?TableOfContentsStyle $style = null,
    ) {
        $this->title = $title;
        $this->baseFont = $baseFont;
        $this->titleSize = $titleSize;
        $this->entrySize = $entrySize;
        $this->margin = $margin;
        $this->placement = $placement ?? TableOfContentsPlacement::end();
        $this->useLogicalPageNumbers = $useLogicalPageNumbers;
        $this->style = $style ?? new TableOfContentsStyle();

        if ($this->titleSize <= 0) {
            throw new InvalidArgumentException('Table of contents title size must be greater than zero.');
        }

        if ($this->entrySize <= 0) {
            throw new InvalidArgumentException('Table of contents entry size must be greater than zero.');
        }

        if ($this->margin < 0) {
            throw new InvalidArgumentException('Table of contents margin must be zero or greater.');
        }
    }
}
