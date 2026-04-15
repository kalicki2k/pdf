<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TableOfContents;

use InvalidArgumentException;

final readonly class TableOfContentsEntry
{
    private function __construct(
        public string $title,
        public int $pageNumber,
        public int $level = 1,
        public ?float $x = null,
        public ?float $y = null,
    ) {
        if ($this->title === '') {
            throw new InvalidArgumentException('Table of contents entry title must not be empty.');
        }

        if ($this->pageNumber < 1) {
            throw new InvalidArgumentException('Table of contents entry page number must be greater than zero.');
        }

        if ($this->level < 1) {
            throw new InvalidArgumentException('Table of contents entry level must be greater than zero.');
        }

        if (($this->x === null) !== ($this->y === null)) {
            throw new InvalidArgumentException('Table of contents entry coordinates must be provided together.');
        }
    }

    public static function page(string $title, int $pageNumber, int $level = 1): self
    {
        return new self($title, $pageNumber, $level);
    }

    public static function position(string $title, int $pageNumber, float $x, float $y, int $level = 1): self
    {
        return new self($title, $pageNumber, $level, $x, $y);
    }

    public function hasPosition(): bool
    {
        return $this->x !== null && $this->y !== null;
    }
}
