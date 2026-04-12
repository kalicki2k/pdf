<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final readonly class Outline
{
    private function __construct(
        public string $title,
        public int $pageNumber,
        public ?float $x = null,
        public ?float $y = null,
    ) {
        if ($this->title === '') {
            throw new InvalidArgumentException('Outline title must not be empty.');
        }

        if ($this->pageNumber < 1) {
            throw new InvalidArgumentException('Outline page number must be greater than zero.');
        }

        if (($this->x === null) !== ($this->y === null)) {
            throw new InvalidArgumentException('Outline coordinates must be provided together.');
        }
    }

    public static function page(string $title, int $pageNumber): self
    {
        return new self($title, $pageNumber);
    }

    public static function position(string $title, int $pageNumber, float $x, float $y): self
    {
        return new self($title, $pageNumber, $x, $y);
    }

    public function hasPosition(): bool
    {
        return $this->x !== null && $this->y !== null;
    }
}
