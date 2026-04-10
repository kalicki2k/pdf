<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;
use Kalle\Pdf\Internal\PdfType\StringType;

final readonly class LaunchAction implements ButtonAction
{
    public function __construct(
        private string $target,
    ) {
        if ($target === '') {
            throw new InvalidArgumentException('Launch action target must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('Launch'),
            'F' => new StringType($this->target),
        ]);
    }
}
