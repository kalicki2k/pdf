<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Table\Style\FooterStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Bundles shared table render dependencies for prepared row-group rendering.
 */
final readonly class TableRenderContext
{
    public function __construct(
        public PreparedCellRenderer $preparedCellRenderer,
        public TableStyle $style,
        public ?RowStyle $rowStyle,
        public ?HeaderStyle $headerStyle,
        public ?FooterStyle $footerStyle,
        public string $baseFont,
        public int $fontSize,
        public float $lineHeightFactor,
        public ?StructElem $tableStructElem,
    ) {
    }

    public function lineHeight(): float
    {
        return $this->fontSize * $this->lineHeightFactor;
    }
}
