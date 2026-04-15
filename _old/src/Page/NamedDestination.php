<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class NamedDestination
{
    private function __construct(
        public string $name,
        public ?float $x = null,
        public ?float $y = null,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Named destination name must not be empty.');
        }
    }

    public static function fit(string $name): self
    {
        return new self($name);
    }

    public static function position(string $name, float $x, float $y): self
    {
        return new self($name, $x, $y);
    }

    public function isFit(): bool
    {
        return $this->x === null && $this->y === null;
    }
}
