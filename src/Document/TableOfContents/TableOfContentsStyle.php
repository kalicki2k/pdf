<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TableOfContents;

use InvalidArgumentException;

final readonly class TableOfContentsStyle
{
    public function __construct(
        public TableOfContentsLeaderStyle $leaderStyle = TableOfContentsLeaderStyle::DOTS,
        public float $entrySpacing = 0.0,
        public float $pageNumberGap = 8.0,
    ) {
        if ($this->entrySpacing < 0) {
            throw new InvalidArgumentException('Table of contents entry spacing must be zero or greater.');
        }

        if ($this->pageNumberGap < 0) {
            throw new InvalidArgumentException('Table of contents page number gap must be zero or greater.');
        }
    }
}
