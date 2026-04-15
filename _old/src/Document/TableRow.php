<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_map;
use function count;

use InvalidArgumentException;

final readonly class TableRow
{
    /**
     * @param list<TableCell> $cells
     */
    public function __construct(
        public array $cells,
    ) {
        if (count($this->cells) === 0) {
            throw new InvalidArgumentException('A table row must contain at least one cell.');
        }
    }

    public static function fromCells(TableCell ...$cells): self
    {
        /** @var list<TableCell> $cells */
        return new self($cells);
    }

    public static function fromTexts(string ...$texts): self
    {
        $cells = array_map(
            static fn (string $text): TableCell => TableCell::text($text),
            $texts,
        );
        /** @var list<TableCell> $cells */

        return new self($cells);
    }
}
