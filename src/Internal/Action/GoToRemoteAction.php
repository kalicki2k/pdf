<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

final readonly class GoToRemoteAction implements ButtonAction
{
    public function __construct(
        private string $file,
        private string $destination,
    ) {
        if ($file === '') {
            throw new InvalidArgumentException('GoTo remote action file must not be empty.');
        }

        if ($destination === '') {
            throw new InvalidArgumentException('GoTo remote action destination must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('GoToR'),
            'F' => new StringType($this->file),
            'D' => new NameType($this->destination),
        ]);
    }
}
