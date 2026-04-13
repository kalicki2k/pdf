<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_unique;

use InvalidArgumentException;

final readonly class OptionalContentConfiguration
{
    /**
     * @param list<string> $order
     * @param list<string> $initialOn
     * @param list<string> $initialOff
     */
    public function __construct(
        public string $name,
        public array $order,
        public array $initialOn = [],
        public array $initialOff = [],
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Optional content configuration name must not be empty.');
        }

        if ($this->order === []) {
            throw new InvalidArgumentException('Optional content configuration must order at least one group alias.');
        }

        foreach ([...$this->order, ...$this->initialOn, ...$this->initialOff] as $alias) {
            if ($alias === '') {
                throw new InvalidArgumentException('Optional content configuration aliases must not be empty.');
            }
        }

        if (array_unique($this->order) !== $this->order) {
            throw new InvalidArgumentException('Optional content configuration order aliases must be unique.');
        }
    }
}
