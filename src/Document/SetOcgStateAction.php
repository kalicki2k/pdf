<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final readonly class SetOcgStateAction implements ButtonAction
{
    private const STATE_OPERATORS = ['ON', 'OFF', 'Toggle'];

    /**
     * @param list<string|OptionalContentGroup> $state
     */
    public function __construct(
        private array $state,
        private bool $preserveRb = true,
    ) {
        if ($this->state === []) {
            throw new InvalidArgumentException('Set OCG state action requires at least one state entry.');
        }

        foreach ($this->state as $entry) {
            if ($entry instanceof OptionalContentGroup) {
                continue;
            }

            if (!in_array($entry, self::STATE_OPERATORS, true)) {
                throw new InvalidArgumentException('Set OCG state action accepts only ON, OFF, Toggle or OptionalContentGroup entries.');
            }
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'S' => new NameType('SetOCGState'),
            'State' => new ArrayType(array_map(
                static fn (string|OptionalContentGroup $entry): NameType|ReferenceType => $entry instanceof OptionalContentGroup
                    ? new ReferenceType($entry)
                    : new NameType($entry),
                $this->state,
            )),
        ]);

        if ($this->preserveRb === false) {
            $dictionary->add('PreserveRB', 'false');
        }

        return $dictionary;
    }
}
