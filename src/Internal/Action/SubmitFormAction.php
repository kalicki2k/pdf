<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;
use Kalle\Pdf\Internal\PdfType\StringType;

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
