<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\StringType;

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
