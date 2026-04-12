<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Layout\Table\ColumnWidth;

final readonly class TableColumn
{
    public function __construct(
        public ColumnWidth $width,
    ) {
    }

    public static function fixed(float $width): self
    {
        return new self(ColumnWidth::fixed($width));
    }

    public static function proportional(float $weight): self
    {
        return new self(ColumnWidth::proportional($weight));
    }
}
