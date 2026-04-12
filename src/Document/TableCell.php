<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final readonly class TableCell
{
    public function __construct(
        public string $text,
        public int $colspan = 1,
        public int $rowspan = 1,
    ) {
        if ($this->colspan < 1) {
            throw new InvalidArgumentException('Table cell colspan must be at least 1.');
        }

        if ($this->rowspan < 1) {
            throw new InvalidArgumentException('Table cell rowspan must be at least 1.');
        }
    }

    public static function text(string $text, int $colspan = 1, int $rowspan = 1): self
    {
        return new self($text, $colspan, $rowspan);
    }

    public function withColspan(int $colspan): self
    {
        return new self($this->text, $colspan, $this->rowspan);
    }

    public function withRowspan(int $rowspan): self
    {
        return new self($this->text, $this->colspan, $rowspan);
    }
}
