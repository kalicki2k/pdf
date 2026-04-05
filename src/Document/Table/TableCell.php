<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table;

use Kalle\Pdf\Document\Table\Style\CellStyle;
use Kalle\Pdf\Document\Text\TextSegment;

final readonly class TableCell
{
    /**
     * @param string|list<TextSegment> $text
     */
    public function __construct(
        public string | array $text,
        public int $colspan = 1,
        public int $rowspan = 1,
        public ?CellStyle $style = null,
    ) {
    }
}
