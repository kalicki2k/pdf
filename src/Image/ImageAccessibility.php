<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use InvalidArgumentException;

final readonly class ImageAccessibility
{
    private function __construct(
        public ?string $altText = null,
        public bool $decorative = false,
    ) {
        if ($this->decorative && $this->altText !== null) {
            throw new InvalidArgumentException('Decorative images cannot define alternative text.');
        }

        if ($this->altText === '') {
            throw new InvalidArgumentException('Alternative text must not be empty.');
        }
    }

    public static function decorative(): self
    {
        return new self(decorative: true);
    }

    public static function alternativeText(string $altText): self
    {
        return new self(altText: $altText);
    }

    public function requiresFigureTag(): bool
    {
        return !$this->decorative;
    }
}
