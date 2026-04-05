<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

final readonly class JavaScriptAction implements ButtonAction
{
    public function __construct(
        private string $script,
    ) {
        if ($script === '') {
            throw new InvalidArgumentException('JavaScript action script must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('JavaScript'),
            'JS' => new StringType($this->script),
        ]);
    }
}
