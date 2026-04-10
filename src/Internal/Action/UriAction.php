<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\StringType;

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
