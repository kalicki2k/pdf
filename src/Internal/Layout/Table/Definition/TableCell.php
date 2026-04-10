<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Definition;

use Kalle\Pdf\Internal\Layout\Table\Style\CellStyle;
use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;

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
