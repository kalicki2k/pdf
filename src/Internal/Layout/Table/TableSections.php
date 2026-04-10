<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table;

use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;

/**
 * @internal Tracks configured table sections and their render progress.
 */
final class TableSections
{
    /** @var list<list<string|list<TextSegment>|TableCell>> */
    private array $repeatingHeaderRows = [];

    /** @var list<list<string|list<TextSegment>|TableCell>> */
    private array $footerRows = [];

    private bool $captionRendered = false;
    private bool $footersRendered = false;
    private bool $rowsAdded = false;
    private bool $bodyRowsAdded = false;
    private bool $footerRowsAdded = false;

    public function canConfigureCaption(): bool
    {
        return !$this->captionRendered && !$this->rowsAdded;
    }

    public function canAddHeaderRows(): bool
    {
        return !$this->bodyRowsAdded && !$this->footerRowsAdded;
    }

    public function markBodyRowsAdded(): void
    {
        $this->bodyRowsAdded = true;
    }

    public function markRowsAdded(): void
    {
        $this->rowsAdded = true;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addRepeatingHeaderRow(array $cells): void
    {
        $this->repeatingHeaderRows[] = $cells;
    }

    /**
     * @return list<list<string|list<TextSegment>|TableCell>>
     */
    public function repeatingHeaderRows(): array
    {
        return $this->repeatingHeaderRows;
    }

    public function hasRepeatingHeaderRows(): bool
    {
        return $this->repeatingHeaderRows !== [];
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addFooterRow(array $cells): void
    {
        $this->rowsAdded = true;
        $this->footerRowsAdded = true;
        $this->footerRows[] = $cells;
    }

    /**
     * @return list<list<string|list<TextSegment>|TableCell>>
     */
    public function footerRows(): array
    {
        return $this->footerRows;
    }

    public function hasFooterRows(): bool
    {
        return $this->footerRows !== [];
    }

    public function isCaptionRendered(): bool
    {
        return $this->captionRendered;
    }

    public function markCaptionRendered(): void
    {
        $this->captionRendered = true;
    }

    public function areFootersRendered(): bool
    {
        return $this->footersRendered;
    }

    public function markFootersRendered(): void
    {
        $this->footersRendered = true;
    }
}
