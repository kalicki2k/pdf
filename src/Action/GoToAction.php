<?php

declare(strict_types=1);

namespace Kalle\Pdf\Action;

use InvalidArgumentException;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;

final readonly class GoToAction implements ButtonAction
{
    public function __construct(
        private string $destination,
    ) {
        if ($destination === '') {
            throw new InvalidArgumentException('GoTo action destination must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('GoTo'),
            'D' => new NameType($this->destination),
        ]);
    }
}
