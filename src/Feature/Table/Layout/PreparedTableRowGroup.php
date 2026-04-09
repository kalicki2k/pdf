<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Layout;

/**
 * @internal Bundles prepared table rows with their resolved heights.
 */
final readonly class PreparedTableRowGroup
{
    /**
     * @param list<PreparedTableRow> $rows
     * @param list<float> $rowHeights
     */
    public function __construct(
        public array $rows,
        public array $rowHeights,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function slice(int $offset): self
    {
        return new self(
            array_slice($this->rows, $offset),
            array_slice($this->rowHeights, $offset),
        );
    }
}
