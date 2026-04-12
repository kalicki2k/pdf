<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final readonly class ListOptions
{
    public function __construct(
        public ListType $type = ListType::BULLET,
        public ?string $marker = null,
        public int $start = 1,
    ) {
        if ($this->marker !== null && $this->marker === '') {
            throw new InvalidArgumentException('List marker must not be empty.');
        }

        if ($this->start < 1) {
            throw new InvalidArgumentException('List numbering start must be greater than or equal to 1.');
        }

        if ($this->type === ListType::NUMBERED && $this->marker !== null && !str_contains($this->marker, '%d')) {
            throw new InvalidArgumentException('Numbered list marker must contain a "%d" placeholder.');
        }
    }
}
