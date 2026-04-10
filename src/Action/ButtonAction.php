<?php

declare(strict_types=1);

namespace Kalle\Pdf\Action;

use Kalle\Pdf\PdfType\DictionaryType;

interface ButtonAction
{
    public function toPdfDictionary(): DictionaryType;
}
