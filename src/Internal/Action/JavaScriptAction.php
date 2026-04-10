<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\StringType;

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
