<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;
use Kalle\Pdf\Internal\PdfType\StringType;

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
