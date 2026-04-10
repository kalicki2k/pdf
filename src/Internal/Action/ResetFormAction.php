<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;

final readonly class ResetFormAction implements ButtonAction
{
    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('ResetForm'),
        ]);
    }
}
