<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Action;

use InvalidArgumentException;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

final readonly class UriAction implements ButtonAction
{
    public function __construct(
        private string $url,
    ) {
        if ($url === '') {
            throw new InvalidArgumentException('URI action URL must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('URI'),
            'URI' => new StringType($this->url),
        ]);
    }
}
