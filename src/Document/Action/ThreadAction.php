<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Action;

use InvalidArgumentException;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

final readonly class ThreadAction implements ButtonAction
{
    public function __construct(
        private string $destination,
        private ?string $file = null,
    ) {
        if ($this->destination === '') {
            throw new InvalidArgumentException('Thread action destination must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'S' => new NameType('Thread'),
            'D' => new StringType($this->destination),
        ]);

        if ($this->file !== null) {
            $dictionary->add('F', new StringType($this->file));
        }

        return $dictionary;
    }
}
