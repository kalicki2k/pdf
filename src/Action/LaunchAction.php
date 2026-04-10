<?php

declare(strict_types=1);

namespace Kalle\Pdf\Action;

use InvalidArgumentException;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

final readonly class LaunchAction implements ButtonAction
{
    public function __construct(
        private string $target,
    ) {
        if ($target === '') {
            throw new InvalidArgumentException('Launch action target must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('Launch'),
            'F' => new StringType($this->target),
        ]);
    }
}
