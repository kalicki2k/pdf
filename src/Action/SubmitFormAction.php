<?php

declare(strict_types=1);

namespace Kalle\Pdf\Action;

use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

final readonly class SubmitFormAction implements ButtonAction
{
    public function __construct(
        private string $url,
        private int $flags = 0,
    ) {
    }

    public function toPdfDictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'S' => new NameType('SubmitForm'),
            'F' => new StringType($this->url),
        ]);

        if ($this->flags > 0) {
            $dictionary->add('Flags', $this->flags);
        }

        return $dictionary;
    }
}
