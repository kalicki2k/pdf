<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Action;

use Kalle\Pdf\Types\DictionaryType;

interface ButtonAction
{
    public function toPdfDictionary(): DictionaryType;
}
