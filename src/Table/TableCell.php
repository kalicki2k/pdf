<?php

declare(strict_types=1);

namespace Kalle\Pdf\Table;

use Kalle\Pdf\Table\Style\CellStyle;
use Kalle\Pdf\Text\TextSegment;

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
        public ?TableHeaderScope $headerScope = null,
    ) {
    }
}
