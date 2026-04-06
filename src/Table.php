<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use Kalle\Pdf\Document\Table as InternalTable;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\Text\TextSegment;

/**
 * Public facade for table layout and rendering.
 */
final readonly class Table
{
    /**
     * @internal Tables are created by Page::createTable().
     */
    public function __construct(private InternalTable $table)
    {
    }

    public function font(string $baseFont, int $size): self
    {
        $this->table->font($baseFont, $size);

        return $this;
    }

    public function style(TableStyle $style): self
    {
        $this->table->style($style);

        return $this;
    }

    public function rowStyle(RowStyle $style): self
    {
        $this->table->rowStyle($style);

        return $this;
    }

    public function headerStyle(HeaderStyle $style): self
    {
        $this->table->headerStyle($style);

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addRow(array $cells, bool $header = false): self
    {
        $this->table->addRow($cells, $header);

        return $this;
    }

    public function getPage(): Page
    {
        return new Page($this->table->getPage());
    }

    public function getCursorY(): float
    {
        return $this->table->getCursorY();
    }
}
