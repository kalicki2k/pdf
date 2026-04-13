<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class OptionalContentGroup
{
    public function __construct(
        public string $name,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Optional content group name must not be empty.');
        }
    }

    public function key(): string
    {
        return $this->name;
    }
}
