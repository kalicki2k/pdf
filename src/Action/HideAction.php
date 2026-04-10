<?php

declare(strict_types=1);

namespace Kalle\Pdf\Action;

use InvalidArgumentException;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

final readonly class HideAction implements ButtonAction
{
    public function __construct(
        private string $target,
        private bool $hide = true,
    ) {
        if ($this->target === '') {
            throw new InvalidArgumentException('Hide action target must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'S' => new NameType('Hide'),
            'T' => new StringType($this->target),
        ]);

        if ($this->hide === false) {
            $dictionary->add('H', 'false');
        }

        return $dictionary;
    }
}
