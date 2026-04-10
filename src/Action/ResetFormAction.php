<?php

declare(strict_types=1);

namespace Kalle\Pdf\Action;

use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;

final readonly class ResetFormAction implements ButtonAction
{
    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('ResetForm'),
        ]);
    }
}
