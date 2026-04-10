<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use Kalle\Pdf\PdfType\DictionaryType;

interface ButtonAction
{
    public function toPdfDictionary(): DictionaryType;
}
