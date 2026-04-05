<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;

final readonly class SetOcgStateAction implements ButtonAction
{
    /**
     * @param list<string> $state
     */
    public function __construct(
        private array $state,
        private bool $preserveRb = true,
    ) {
        if ($this->state === []) {
            throw new InvalidArgumentException('Set OCG state action requires at least one state entry.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'S' => new NameType('SetOCGState'),
            'State' => new ArrayType(array_map(
                static fn (string $entry): NameType => new NameType($entry),
                $this->state,
            )),
        ]);

        if ($this->preserveRb === false) {
            $dictionary->add('PreserveRB', 'false');
        }

        return $dictionary;
    }
}
