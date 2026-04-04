<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final readonly class TableCell
{
    /**
     * @param string|list<TextSegment> $text
     */
    public function __construct(
        public string|array $text,
        public TextAlign $align = TextAlign::LEFT,
        public ?Color $fillColor = null,
        public ?Color $textColor = null,
        public ?Opacity $opacity = null,
        public int $colspan = 1,
        public int $rowspan = 1,
    ) {
    }
}
