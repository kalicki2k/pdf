<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function array_unique;

use InvalidArgumentException;

final readonly class OptionalContentStateAction
{
    /**
     * @param list<string> $turnOn
     * @param list<string> $turnOff
     * @param list<string> $toggle
     */
    public function __construct(
        public array $turnOn = [],
        public array $turnOff = [],
        public array $toggle = [],
        public bool $preserveRadioButtons = true,
    ) {
        if ($this->turnOn === [] && $this->turnOff === [] && $this->toggle === []) {
            throw new InvalidArgumentException('Optional content state action must contain at least one target alias.');
        }

        $allAliases = [...$this->turnOn, ...$this->turnOff, ...$this->toggle];

        foreach ($allAliases as $alias) {
            if ($alias === '') {
                throw new InvalidArgumentException('Optional content state action aliases must not be empty.');
            }
        }

        if (array_unique($allAliases) !== $allAliases) {
            throw new InvalidArgumentException('Optional content state action aliases must be unique across all state buckets.');
        }
    }
}
